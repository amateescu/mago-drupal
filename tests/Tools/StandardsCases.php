<?php

/**
 * Runs the cases that show how each sentence of the standards map is
 * handled.
 *
 * A sentence of `tests/data/standards-map.json` names its cases in `cases`.
 * A case is a file under `tests/standards/`, named without its extension,
 * such as `coding/short_array` for `tests/standards/coding/short_array.php`.
 * The file holds halves, each below a marker line:
 *
 * - `// Reported.` is code that breaks the sentence. Every rule and analyzer
 *   code in the sentence's `where` must report in the reported half of one
 *   of its cases.
 * - `// Fine.` is code that follows the sentence. None of them may report
 *   there.
 * A sentence that allows something only has fine code.
 *
 * - `// Before.` and `// After.` are code that `mago format` must turn from
 *   the first into the second. A sentence with `format` in its `where` has
 *   such a case. `// After.` alone is code that the formatter leaves as it
 *   is.
 *
 * The text above the first marker starts every half. A file that doesn't
 * have any gets `<?php`, and a file docblock unless it is a `.php` file. A
 * YAML file writes its markers with `#`. A case that needs several files,
 * or a file at a path, is a directory with `reported/`, `fine/`, `before/`
 * and `after/` directories in place of the halves.
 *
 * All cases run together in a temporary workspace whose config extends
 * `drupal.mago.toml` and loads this checkout's worker, so a case shows what
 * a project with the preset gets.
 *
 * Usage: php tests/Tools/StandardsCases.php <mago binary> [--show <case> | --update]
 *
 * `--show` prints the issues and the formatted text of one case. `--update`
 * writes the After half of every one-file case from what the formatter gives
 * for its Before half. Read the result before committing it.
 */

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests\StandardsCases;

use amateescu\MagoDrupal\Internal\Type\Shape;
use amateescu\MagoDrupal\Tests\StandardsMap;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function array_diff;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_unique;
use function array_values;
use function basename;
use function count;
use function dirname;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function fwrite;
use function getmypid;
use function glob;
use function implode;
use function in_array;
use function is_dir;
use function json_decode;
use function ltrim;
use function mkdir;
use function pathinfo;
use function preg_split;
use function proc_close;
use function proc_open;
use function putenv;
use function realpath;
use function rmdir;
use function rtrim;
use function scandir;
use function sort;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function stream_get_contents;
use function strlen;
use function strpos;
use function substr;
use function sys_get_temp_dir;
use function trim;
use function unlink;

use const PATHINFO_EXTENSION;
use const PREG_SPLIT_DELIM_CAPTURE;
use const STDERR;
use const STDOUT;

const KINDS = ['Reported' => 'reported', 'Fine' => 'fine', 'Before' => 'before', 'After' => 'after'];

/**
 * Runs a command and returns its standard output.
 *
 * @param list<string> $command
 */
function run(array $command): string
{
    $pipes = [];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if ($process === false) {
        return '';
    }

    $streams = [];
    foreach ([1, 2] as $pipe) {
        $stream = $pipes[$pipe] ?? null;
        $streams[] = $stream === null ? '' : (string) stream_get_contents($stream);
    }

    proc_close($process);

    return $streams[0];
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

    $entries = scandir($path);
    foreach (array_diff($entries === false ? [] : $entries, ['.', '..']) as $file) {
        remove($path . '/' . $file);
    }

    rmdir($path);
}

/**
 * Writes a file of a case into the workspace.
 */
function put(string $workspace, string $kind, string $case, string $file, string $text): void
{
    $path = "{$workspace}/{$kind}/{$case}/{$file}";
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), recursive: true);
    }

    file_put_contents($path, $text);
}

/**
 * Returns the files of a case by half, each as a path below the case mapped
 * to its text, or null when the case doesn't exist.
 *
 * @return array<string, array<string, string>>|null
 */
function halves(string $root, string $case): ?array
{
    $base = "{$root}/tests/standards/{$case}";
    if (is_dir($base)) {
        $halves = [];
        foreach (KINDS as $kind) {
            if (!is_dir("{$base}/{$kind}")) {
                continue;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator("{$base}/{$kind}", FilesystemIterator::SKIP_DOTS),
            );
            /** @var SplFileInfo $file */
            foreach ($files as $file) {
                $relative = substr($file->getPathname(), strlen("{$base}/{$kind}/"));
                $halves[$kind][$relative] = (string) file_get_contents($file->getPathname());
            }
        }

        return $halves;
    }

    $matches = glob($base . '.*');
    $path = $matches === false ? null : $matches[0] ?? null;
    if ($path === null) {
        return null;
    }

    $yaml = str_ends_with($path, '.yml');
    $comment = $yaml ? '#' : '//';
    $parts = (array) preg_split(
        '~^' . $comment . ' (Reported|Fine|Before|After)\.\n~m',
        (string) file_get_contents($path),
        flags: PREG_SPLIT_DELIM_CAPTURE,
    );
    $header = (string) $parts[0];
    if (trim($header) === '') {
        $header = match (true) {
            $yaml => '',
            str_ends_with($path, '.php') => "<?php\n\n",
            default => "<?php\n\n/**\n * @file\n * Holds a case.\n */\n\n",
        };
    }

    $halves = [];
    for ($index = 1; $index < count($parts); $index += 2) {
        $kind = KINDS[(string) $parts[$index]];
        $halves[$kind][basename($path)] = $header . trim((string) ($parts[$index + 1] ?? ''), characters: "\n") . "\n";
    }

    return $halves;
}

/**
 * Returns the codes that `mago lint` or `mago analyze` reports in the
 * workspace, by half and case.
 *
 * @param list<string> $command
 * @param list<string> $cases
 *
 * @return array<string, array<string, list<string>>>|null
 */
function issues(array $command, array $cases): ?array
{
    $decoded = Shape::array(json_decode(run([...$command, '--reporting-format', 'json']), associative: true));
    $issues = Shape::array($decoded['issues'] ?? null);
    if ($issues === null) {
        return null;
    }

    $found = [];
    /** @var mixed $issue */
    foreach ($issues as $issue) {
        $file = Shape::stringAt($issue, 'annotations', '0', 'span', 'file_id', 'name') ?? '';
        $kind = explode('/', $file, limit: 2)[0];
        foreach ($cases as $case) {
            if (!str_starts_with($file, "{$kind}/{$case}/")) {
                continue;
            }

            $found[$kind][$case][] = Shape::stringAt($issue, 'code') ?? '';
        }
    }

    return $found;
}

$mago = $argv[1] ?? null;
if ($mago === null) {
    fwrite(STDERR, data: "Usage: php tests/Tools/StandardsCases.php <mago binary> [--show <case> | --update]\n");
    exit(2);
}

$resolved = realpath($mago);
$mago = $resolved === false ? $mago : $resolved;
$show = ($argv[2] ?? '') === '--show' ? $argv[3] ?? '' : null;
$update = ($argv[2] ?? '') === '--update';

require dirname(__DIR__, levels: 2) . '/vendor/autoload.php';

$root = dirname(__DIR__, levels: 2);
$map = StandardsMap::load($root . '/tests/data/standards-map.json');

// The cases of every sentence, and the files of every case.
$sentences = [];
$cases = [];
$problems = [];
foreach ($map->pages as $page) {
    foreach ($page['sections'] as $section) {
        foreach ($section['sentences'] as $sentence) {
            $names = $sentence['cases'] ?? [];
            $where = array_values(array_diff($sentence['where'] ?? [], ['preset']));
            $where = array_values(array_filter(
                $where,
                static fn(string $handler): bool => !str_starts_with($handler, 'check:'),
            ));
            if ($names === [] && $where !== []) {
                $problems[] = "\"{$sentence['quotes'][0]}\" names a handler and doesn't have a case.";
            }

            if ($names === []) {
                continue;
            }

            $sentences[] = [$sentence['quotes'][0], $where, $names, $sentence['status'] === 'allowed'];
            foreach ($names as $name) {
                $cases[$name] ??= halves($root, $name);
                if ($cases[$name] === null) {
                    $problems[] = "{$name}: the case of \"{$sentence['quotes'][0]}\" doesn't exist.";
                }
            }
        }
    }
}

// A case file that isn't named by any sentence.
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
    $root . '/tests/standards',
    FilesystemIterator::SKIP_DOTS,
));
/** @var SplFileInfo $file */
foreach ($files as $file) {
    $relative = substr($file->getPathname(), strlen($root . '/tests/standards/'));
    $owned = false;
    foreach (array_keys($cases) as $name) {
        $owned = $owned || str_starts_with($relative, $name . '.') || str_starts_with($relative, $name . '/');
    }

    if (!$owned) {
        $problems[] = "tests/standards/{$relative} isn't a case of any sentence.";
    }
}

putenv('MAGO_DRUPAL_CACHE=0');
$workspace = sys_get_temp_dir() . '/mago-drupal-standards-' . (string) getmypid();
mkdir($workspace);
$extensions = [];
foreach ($cases as $name => $halves) {
    foreach ($halves ?? [] as $kind => $files) {
        foreach ($files as $file => $text) {
            $extensions[] = pathinfo($file, PATHINFO_EXTENSION);
            put($workspace, $kind, $name, $file, $text);
            if ($kind === 'before') {
                put($workspace, kind: 'formatted', case: $name, file: $file, text: $text);
            }

            if ($kind === 'after' && !array_key_exists($file, $halves['before'] ?? [])) {
                put($workspace, kind: 'formatted', case: $name, file: $file, text: $text);
            }
        }
    }
}

foreach (['reported', 'fine', 'formatted'] as $kind) {
    if (is_dir("{$workspace}/{$kind}")) {
        continue;
    }

    mkdir("{$workspace}/{$kind}");
}

file_put_contents($workspace . '/mago.toml', sprintf(
    "version = \"1\"\nextends = \"%s/drupal.mago.toml\"\nphp-version = \"8.3\"\n\n[source]\npaths = [\"reported\", \"fine\", \"formatted\"]\n\n[extension-hosts.drupal]\ncommand = [\"php\", \"-d\", \"xdebug.mode=off\", \"-d\", \"pcov.enabled=0\", \"%s/resources/worker.php\"]\n",
    $root,
    $root,
));
$base = [$mago, '--workspace', $workspace, '--config', $workspace . '/mago.toml'];
$names = array_keys($cases);
$lint = issues([...$base, 'lint'], $names);
$analyze = issues([...$base, 'analyze'], $names);
run([...$base, 'format']);
if ($lint === null || $analyze === null) {
    fwrite(STDERR, data: "mago didn't give a report.\n");
    remove($workspace);
    exit(1);
}

if ($show !== null) {
    foreach (['reported', 'fine'] as $kind) {
        $codes = [
            ...($lint[$kind][$show] ?? []),
            ...array_map(static fn(string $code): string => 'analyze:' . $code, $analyze[$kind][$show] ?? []),
        ];
        sort($codes);
        fwrite(STDOUT, "{$kind}: " . implode(', ', array_unique($codes)) . "\n");
    }

    foreach (array_keys(($cases[$show] ?? [])['before'] ?? ($cases[$show] ?? [])['after'] ?? []) as $file) {
        fwrite(
            STDOUT,
            "--- formatted {$file}\n" . (string) file_get_contents("{$workspace}/formatted/{$show}/{$file}"),
        );
    }

    remove($workspace);
    exit(0);
}

foreach ($sentences as [$quote, $where, $names, $allowed]) {
    foreach ($where as $handler) {
        [$tool, $code] = [...explode(':', $handler, limit: 2), ''];
        $proofs = 0;
        foreach ($names as $name) {
            $halves = $cases[$name] ?? [];
            if ($tool === 'format') {
                $proofs += array_key_exists('after', $halves) ? 1 : 0;

                continue;
            }

            $found = $tool === 'analyze' ? $analyze : $lint;
            $proofs += in_array($code, $found['reported'][$name] ?? [], strict: true) ? 1 : 0;
            if (in_array($code, $found['fine'][$name] ?? [], strict: true)) {
                $problems[] = "{$name}: {$code} reports the fine code of \"{$quote}\".";
            }
        }

        // A sentence that allows something only needs code that isn't
        // reported.
        $proven = $proofs > 0 || $allowed && $tool !== 'format';
        if (!$proven) {
            $problems[] = $tool === 'format'
                ? "\"{$quote}\": no case has an After half for mago format."
                : "\"{$quote}\": {$code} doesn't report the reported code of " . implode(', ', $names) . '.';
        }
    }
}

// With --update, the After half of a one-file case is written from what the
// formatter gives for its Before half.
foreach ($update ? $cases : [] as $name => $halves) {
    $matches = glob("{$root}/tests/standards/{$name}.*");
    $path = $matches === false ? null : $matches[0] ?? null;
    $before = ($halves ?? [])['before'] ?? [];
    if ($path === null || $before === []) {
        continue;
    }

    $file = basename($path);
    $text = (string) file_get_contents($path);
    $comment = str_ends_with($path, '.yml') ? '#' : '//';
    $marker = "{$comment} Before.\n";
    $start = (int) strpos($text, $marker);
    $body = trim(
        explode("\n{$comment} After.\n", substr($text, $start + strlen($marker)), limit: 2)[0],
        characters: "\n",
    );
    $header = rtrim(substr($before[$file], offset: 0, length: (int) strpos($before[$file], $body)));
    $formatted = (string) file_get_contents("{$workspace}/formatted/{$name}/{$file}");
    $after = str_starts_with($formatted, $header) ? substr($formatted, strlen($header)) : $formatted;
    file_put_contents(
        $path,
        substr($text, offset: 0, length: $start) . "{$marker}{$body}\n\n{$comment} After.\n"
            . ltrim($after, characters: "\n"),
    );
    $cases[$name] = halves($root, $name);
}

foreach ($cases as $name => $halves) {
    foreach (($halves ?? [])['after'] ?? [] as $file => $expected) {
        $got = (string) file_get_contents("{$workspace}/formatted/{$name}/{$file}");
        if (rtrim($got) !== rtrim($expected)) {
            $problems[] = "{$name}: mago format doesn't give the After half of {$file}.\n--- got\n{$got}";
        }
    }
}

remove($workspace);
if ($problems !== []) {
    fwrite(
        STDERR,
        implode("\n", $problems) . "\n" . (string) count($problems) . " problem(s) in the standards cases.\n",
    );
    exit(1);
}

fwrite(STDOUT, (string) count($cases) . " standards cases hold.\n");
