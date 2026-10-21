<?php

/**
 * Checks the examples in the analyzer docs.
 *
 * An entry on a page in `docs/analyzer/` may hold an `??? example` or a
 * `???+ example` block with one or more `php` or `yaml` code blocks. A block
 * with a `// Fine.` line, `# Fine.` in YAML, splits into a reported half and a
 * fine half, as in the rule docs, and a `// Reported` or `# Reported` comment
 * line at its top is not part of the code. A block without a `Fine.` line
 * belongs to both halves, such as the services file that a lookup needs. At
 * least one block splits. The reported half must get at least one issue from
 * the entry's check, at a level that the entry's Level line names, and the
 * fine half must not get any.
 *
 * Each half runs on its own, in a temporary workspace with the test corpus's
 * stand-ins for Drupal core (`tests/corpus/core` and `tests/corpus/stubs`)
 * and a module named `example`. A code block runs as
 * `modules/example/example.module`, or at the path that its `title` gives,
 * from the workspace root. A YAML block needs a title.
 *
 * Usage: php tests/Tools/CheckExamples.php <mago binary> [page.md...]
 *
 * With page names, such as `services.md`, only those pages are checked.
 */

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests\CheckExamples;

use function array_key_exists;
use function array_map;
use function array_slice;
use function basename;
use function count;
use function dirname;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function fwrite;
use function getenv;
use function getmypid;
use function glob;
use function implode;
use function in_array;
use function is_array;
use function is_dir;
use function json_decode;
use function mkdir;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_replace;
use function preg_split;
use function proc_close;
use function proc_open;
use function realpath;
use function rmdir;
use function scandir;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function stream_get_contents;
use function strtolower;
use function substr;
use function sys_get_temp_dir;
use function trim;
use function unlink;

use const PREG_SET_ORDER;
use const STDERR;
use const STDOUT;

const MODULE = 'modules/example';

const CONFIG = <<<'TOML'
    version = "1"
    php-version = "8.3"

    [source]
    paths = ["modules"]
    includes = ["core", "stubs"]
    extensions = ["php", "module", "install", "inc", "theme"]

    [extension-hosts.drupal]
    command = ["php", "-d", "xdebug.mode=off", "-d", "pcov.enabled=0", "%s"]

    TOML;

/**
 * Runs a command and returns its exit status, its standard output and its
 * error output. The worker builds its indexes from scratch, so a workspace
 * doesn't leave anything in the cache.
 *
 * @param list<string> $command
 *
 * @return array{int, string, string}
 */
function run(array $command): array
{
    $environment = getenv();
    $environment['MAGO_DRUPAL_CACHE'] = '0';
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, env_vars: $environment);
    if ($process === false) {
        return [-1, '', ''];
    }

    $output = (string) stream_get_contents($pipes[1]);
    $errors = (string) stream_get_contents($pipes[2]);

    return [proc_close($process), $output, $errors];
}

/**
 * Copies a directory, or deletes one when the target is null.
 */
function tree(string $from, ?string $to): void
{
    if (!is_dir($from)) {
        $to === null ? unlink($from) : file_put_contents($to, (string) file_get_contents($from));

        return;
    }

    if ($to !== null) {
        mkdir($to, recursive: true);
    }

    foreach (scandir($from) ?: [] as $file) {
        if ($file !== '.' && $file !== '..') {
            tree($from . '/' . $file, $to === null ? null : $to . '/' . $file);
        }
    }

    if ($to === null) {
        rmdir($from);
    }
}

/**
 * Removes the four spaces of indent from the lines of an example block.
 */
function dedent(string $block): string
{
    return implode("\n", array_map(
        static fn(string $line): string => substr($line, offset: 4),
        explode("\n", $block),
    ));
}

/**
 * Returns the files of the reported half and the fine half of an example,
 * keyed by path, or what is wrong with the example.
 *
 * @return array{reported: array<string, string>, fine: array<string, string>}|string
 */
function halves(string $text): array|string
{
    $blocks = [];
    preg_match_all('/^```(php|yaml)(?: title="([^"]+)")?\n(.*?)^```$/ms', $text, $blocks, PREG_SET_ORDER);
    if ($blocks === []) {
        return 'no php or yaml code block';
    }

    $files = ['reported' => [], 'fine' => []];
    $split = false;
    foreach ($blocks as [, $language, $title, $body]) {
        if ($language === 'yaml' && $title === '') {
            return 'a YAML block with no title';
        }

        $comment = $language === 'yaml' ? '#' : '//';
        $parts = explode("\n{$comment} Fine.\n", "\n" . $body, 2);
        $reported = (string) preg_replace('/^\n' . preg_quote($comment, '/') . ' Reported.*\n/', '', $parts[0]);
        $split = $split || count($parts) === 2;
        $path = $title !== '' ? $title : MODULE . '/example.module';
        $header = $language === 'php' ? "<?php\n\n" : '';
        $files['reported'][$path] = $header . trim($reported, "\n") . "\n";
        $files['fine'][$path] = $header . trim($parts[1] ?? $reported, "\n") . "\n";
    }

    return $split ? $files : 'no `// Fine.` or `# Fine.` line in any block';
}

/**
 * Analyzes one half in a new workspace and returns the levels of the issues
 * that the check reports on the example's files.
 *
 * @param array<string, string> $files
 *
 * @return list<string>|string The levels, or how the run failed.
 */
function analyze(string $mago, string $root, string $workspace, string $code, array $files): array|string
{
    tree($root . '/tests/corpus/core', $workspace . '/core');
    tree($root . '/tests/corpus/stubs', $workspace . '/stubs');
    $files += [MODULE . '/example.info.yml' => "name: Example\ntype: module\ncore_version_requirement: ^11\n"];
    foreach ($files as $path => $text) {
        if (!is_dir(dirname("{$workspace}/{$path}"))) {
            mkdir(dirname("{$workspace}/{$path}"), recursive: true);
        }

        file_put_contents("{$workspace}/{$path}", $text);
    }

    file_put_contents($workspace . '/mago.toml', sprintf(CONFIG, $root . '/resources/worker.php'));
    [$status, $output, $errors] = run([
        $mago,
        '--workspace',
        $workspace,
        '--config',
        $workspace . '/mago.toml',
        'analyze',
        '--reporting-format',
        'json',
    ]);
    tree($workspace, null);
    $report = json_decode($output, associative: true);
    if (!is_array($report) || !is_array($report['issues'] ?? null)) {
        return "mago failed with status {$status}.\n{$output}{$errors}";
    }

    $levels = [];
    foreach ($report['issues'] as $issue) {
        $file = (string) ($issue['annotations'][0]['span']['file_id']['name'] ?? '');
        if (($issue['code'] ?? '') === $code && array_key_exists($file, $files)) {
            $levels[] = strtolower((string) ($issue['level'] ?? ''));
        }
    }

    return $levels;
}

/**
 * Checks one example and returns how many checks failed.
 */
function check(string $mago, string $root, string $name, string $code, string $level, string $text): int
{
    $halves = halves($text);
    if (!is_array($halves)) {
        fwrite(STDERR, "{$name}: {$halves}.\n");

        return 1;
    }

    $failures = 0;
    $workspace = sys_get_temp_dir() . '/mago-drupal-checks-' . getmypid();
    foreach ($halves as $kind => $files) {
        $levels = analyze($mago, $root, "{$workspace}-{$kind}", $code, $files);
        if (!is_array($levels)) {
            fwrite(STDERR, "{$name}: {$levels}\n");
            $failures++;
        } elseif ($kind === 'fine' && $levels !== []) {
            fwrite(STDERR, "{$name}: the fine code gets " . count($levels) . " issue(s).\n");
            $failures++;
        } elseif ($kind === 'reported' && $levels === []) {
            fwrite(STDERR, "{$name}: the reported code doesn't get an issue.\n");
            $failures++;
        }

        foreach ($kind === 'reported' && is_array($levels) ? $levels : [] as $found) {
            if (!str_contains($level, $found)) {
                fwrite(STDERR, "{$name}: the check reports a {$found}, and the entry's level is {$level}.\n");
                $failures++;
            }
        }
    }

    return $failures;
}

$mago = $argv[1] ?? null;
if ($mago === null) {
    fwrite(STDERR, "Usage: php tests/Tools/CheckExamples.php <mago binary> [page.md...]\n");
    exit(2);
}

// Mago runs with a temporary workspace, so a relative path such as
// vendor/bin/mago has to be resolved first.
if (!str_starts_with($mago, '/')) {
    $mago = realpath($mago) ?: $mago;
}

$root = dirname(__DIR__, levels: 2);
$only = array_slice($argv, offset: 2);
$failures = 0;
$count = 0;
foreach (glob($root . '/docs/analyzer/*.md') ?: [] as $page) {
    if ($only !== [] && !in_array(basename($page), $only, strict: true)) {
        continue;
    }

    $entries = (array) preg_split('/^## (?=(?:drupal|phpunit)\/)/m', (string) file_get_contents($page));
    foreach (array_slice($entries, 1) as $entry) {
        $code = explode("\n", (string) $entry, 2)[0];
        $level = preg_match('/^- \*\*Level:\*\* (.+)$/m', (string) $entry, $match) === 1 ? $match[1] : '';
        $blocks = [];
        preg_match_all('/^\?\?\?\+? example .*\n((?:\n|    .*\n)+)/m', (string) $entry, $blocks, PREG_SET_ORDER);
        foreach ($blocks as $index => [, $block]) {
            $count++;
            $failures += check($mago, $root, "{$code}, example " . ($index + 1), $code, $level, dedent($block));
        }
    }
}

if ($failures > 0) {
    fwrite(STDERR, "{$failures} problem(s) in {$count} analyzer doc examples.\n");
    exit(1);
}

fwrite(STDOUT, "{$count} analyzer doc examples hold.\n");
