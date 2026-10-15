<?php

/**
 * Checks the examples in the rule docs.
 *
 * An entry on a page in `docs/rules/` may hold an `??? example` or a
 * `???+ example` block with one `php` or `yaml` code block. A `// Fine.` line,
 * `# Fine.` in YAML, splits the code into a reported half and a fine half. A
 * `// Reported` or `# Reported` comment line at the top is not part of the
 * code. The reported half must get at least one issue from the entry's rule,
 * and the fine half must not get any. When the block says "The fix turns the
 * reported code into the fine code.", `mago lint --fix` must turn the reported
 * half into the fine half.
 *
 * Each half runs as `example.module`, after a file docblock, in a temporary
 * workspace whose config loads this checkout's worker. A code block with a
 * `title` runs under that path instead. A `.php` title doesn't get a file
 * docblock, and a YAML block, which needs a title such as `example.info.yml`,
 * gets the code alone.
 *
 * Usage: php tests/DocExamples.php <mago binary>
 */

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use function array_key_exists;
use function array_map;
use function array_slice;
use function count;
use function dirname;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function fwrite;
use function getmypid;
use function glob;
use function implode;
use function is_array;
use function is_dir;
use function json_decode;
use function mkdir;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_replace;
use function proc_close;
use function proc_open;
use function realpath;
use function rmdir;
use function rtrim;
use function scandir;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function stream_get_contents;
use function substr;
use function sys_get_temp_dir;
use function trim;
use function unlink;

use const PREG_SET_ORDER;
use const STDERR;
use const STDOUT;

const FIX_CLAIM = 'The fix turns the reported code into the fine code.';

/**
 * Runs a command and returns its exit status, its standard output and its
 * error output.
 *
 * @param list<string> $command
 *
 * @return array{int, string, string}
 */
function run(array $command): array
{
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if ($process === false) {
        return [-1, '', ''];
    }

    $output = (string) stream_get_contents($pipes[1]);
    $errors = (string) stream_get_contents($pipes[2]);

    return [proc_close($process), $output, $errors];
}

/**
 * Deletes a temporary workspace.
 */
function remove(string $path): void
{
    if (!is_dir($path)) {
        unlink($path);

        return;
    }

    foreach (scandir($path) ?: [] as $file) {
        if ($file !== '.' && $file !== '..') {
            remove($path . '/' . $file);
        }
    }

    rmdir($path);
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
 * Returns a half as file text, with a file docblock unless the file is a
 * `.php` or YAML file.
 */
function file_text(string $code, string $path): string
{
    $header = match (true) {
        str_ends_with($path, '.yml') => '',
        str_ends_with($path, '.php') => "<?php\n\n",
        default => "<?php\n\n/**\n * @file\n * Example.\n */\n\n",
    };

    return $header . trim($code, "\n") . "\n";
}

/**
 * Lints the halves of one example and returns how many checks failed.
 *
 * @param list<string> $lint The `mago lint` command for the rule.
 */
function check(string $name, string $rule, array $lint, string $workspace, string $path, string $fine, bool $fix): int
{
    [$status, $output, $errors] = run([...$lint, '--reporting-format', 'json']);
    $report = json_decode($output, associative: true);
    if (!is_array($report) || !is_array($report['issues'] ?? null)) {
        fwrite(STDERR, "{$name}: mago failed with status {$status}.\n{$output}{$errors}\n");

        return 1;
    }

    $found = ['reported' => 0, 'fine' => 0];
    foreach ($report['issues'] as $issue) {
        $kind = explode('/', (string) ($issue['annotations'][0]['span']['file_id']['name'] ?? ''), 2)[0];
        if (($issue['code'] ?? '') === 'drupal/' . $rule && array_key_exists($kind, $found)) {
            $found[$kind]++;
        }
    }

    $failures = 0;
    if ($found['reported'] === 0) {
        fwrite(STDERR, "{$name}: the reported code gets no issue.\n");
        $failures++;
    }

    if ($found['fine'] > 0) {
        fwrite(STDERR, "{$name}: the fine code gets {$found['fine']} issue(s).\n");
        $failures++;
    }

    if (!$fix) {
        return $failures;
    }

    run([...$lint, '--fix', "{$workspace}/fixed/{$path}"]);
    $fixed = (string) file_get_contents("{$workspace}/fixed/{$path}");
    if (rtrim($fixed) !== rtrim(file_text($fine, $path))) {
        fwrite(STDERR, "{$name}: the fix does not give the fine code.\n--- got\n{$fixed}\n");
        $failures++;
    }

    return $failures;
}

$mago = $argv[1] ?? null;
if ($mago === null) {
    fwrite(STDERR, "Usage: php tests/DocExamples.php <mago binary>\n");
    exit(2);
}

// Mago runs with a temporary workspace, so a relative path such as
// vendor/bin/mago has to be resolved first.
if (!str_starts_with($mago, '/')) {
    $mago = realpath($mago) ?: $mago;
}

$root = dirname(__DIR__);
$worker = $root . '/resources/worker.php';
$failures = 0;
$count = 0;
foreach (glob($root . '/docs/rules/*.md') ?: [] as $page) {
    foreach (array_slice(explode("\n## drupal/", (string) file_get_contents($page)), 1) as $entry) {
        $rule = explode("\n", $entry, 2)[0];
        $blocks = [];
        preg_match_all('/^\?\?\?\+? example .*\n((?:\n|    .*\n)+)/m', $entry, $blocks, PREG_SET_ORDER);
        foreach ($blocks as $index => [, $block]) {
            $count++;
            $name = "drupal/{$rule}, example " . ($index + 1);
            $text = dedent($block);
            $code = [];
            if (preg_match('/^```(php|yaml)(?: title="([^"]+)")?\n(.*?)^```$/ms', $text, $code) !== 1) {
                fwrite(STDERR, "{$name}: no php or yaml code block.\n");
                $failures++;
                continue;
            }

            [, $language, $title, $body] = $code;
            $comment = $language === 'yaml' ? '#' : '//';
            $halves = explode("\n{$comment} Fine.\n", "\n" . $body, 2);
            if (count($halves) !== 2 || $language === 'yaml' && $title === '') {
                fwrite(STDERR, "{$name}: no `{$comment} Fine.` line, or a YAML block with no title.\n");
                $failures++;
                continue;
            }

            [$reported, $fine] = $halves;
            $reported = (string) preg_replace('/^\n' . preg_quote($comment, '/') . ' Reported.*\n/', '', $reported);
            $path = $title !== '' ? $title : 'example.module';
            $fix = str_contains($text, FIX_CLAIM);

            $workspace = sys_get_temp_dir() . '/mago-drupal-examples-' . getmypid() . '-' . $count;
            $files = ['reported' => $reported, 'fine' => $fine] + ($fix ? ['fixed' => $reported] : []);
            foreach ($files as $kind => $half) {
                mkdir(dirname("{$workspace}/{$kind}/{$path}"), recursive: true);
                file_put_contents("{$workspace}/{$kind}/{$path}", file_text($half, $path));
            }

            file_put_contents($workspace . '/mago.toml', sprintf(
                "version = \"1\"\nphp-version = \"8.3\"\n\n[source]\npaths = [\"reported\", \"fine\"]\nextensions = [\"php\", \"module\", \"install\", \"inc\", \"theme\", \"yml\"]\n\n[extension-hosts.drupal]\ncommand = [\"php\", \"-d\", \"xdebug.mode=off\", \"-d\", \"pcov.enabled=0\", \"%s\"]\n",
                $worker,
            ));
            $lint = [$mago, '--workspace', $workspace, '--config', $workspace . '/mago.toml', 'lint', '--only', 'drupal/' . $rule];
            $failures += check($name, $rule, $lint, $workspace, $path, $fine, $fix);
            remove($workspace);
        }
    }
}

if ($failures > 0) {
    fwrite(STDERR, "{$failures} problem(s) in {$count} doc examples.\n");
    exit(1);
}

fwrite(STDOUT, "{$count} doc examples hold.\n");
