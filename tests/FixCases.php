<?php

/**
 * Checks the fixes the rules attach to their issues.
 *
 * `tests/fixes/<rule>/` holds pairs of files for the rule `drupal/<rule>`: a
 * `<case>.php` with the problem and a `<case>.fixed.php` with the text that
 * `mago lint --fix` must turn it into. Drupal's other PHP extensions, such as
 * `.module`, work the same way. A case named `<case>.unsafe.php` or
 * `<case>.potentially-unsafe.php` runs with that safety level allowed. Every
 * other case runs with the safe fixes only, so an unsafe fix that a plain
 * `--fix` applies shows up as a difference. A case named `<case>.core.php`
 * runs with the worker's `--core` argument. A case with no change to make
 * has a `.fixed.php` equal to itself.
 *
 * The cases of one rule, safety level and worker argument run together, in a
 * temporary workspace whose config loads this checkout's worker.
 *
 * Usage: php tests/FixCases.php <mago binary> [--update]
 *
 * `--update` writes what the fixes produce into the `.fixed.php` files
 * instead of comparing. Read the result before committing it.
 */

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use function basename;
use function copy;
use function count;
use function dirname;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function fwrite;
use function getmypid;
use function in_array;
use function is_dir;
use function is_file;
use function ksort;
use function mkdir;
use function preg_match;
use function preg_replace;
use function proc_close;
use function proc_open;
use function realpath;
use function rmdir;
use function scandir;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function stream_get_contents;
use function strlen;
use function substr;
use function trim;
use function sys_get_temp_dir;
use function unlink;

use const STDERR;
use const STDOUT;

$mago = $argv[1] ?? null;
if ($mago === null) {
    fwrite(STDERR, "Usage: php tests/FixCases.php <mago binary> [--update]\n");
    exit(2);
}

$update = ($argv[2] ?? '') === '--update';

// The command runs in the temporary workspace, so a relative path such as
// vendor/bin/mago has to be resolved first.
if (!str_starts_with($mago, '/')) {
    $mago = realpath($mago) ?: $mago;
}

/**
 * A case file: its name without the extension, and the extension.
 */
const CASE_NAME = '/^(.+)\.(php|module|install|inc|theme)$/';

$root = dirname(__DIR__);
$worker = $root . '/resources/worker.php';

/**
 * The cases, grouped by rule, by the safety flag they run with, and by the
 * worker's `--core` argument.
 *
 * @var array<string, list<string>> $groups
 */
$groups = [];
foreach (scandir($root . '/tests/fixes') ?: [] as $rule) {
    $directory = $root . '/tests/fixes/' . $rule;
    if ($rule[0] === '.' || !is_dir($directory)) {
        continue;
    }

    foreach (scandir($directory) ?: [] as $file) {
        $parts = [];
        if (preg_match(CASE_NAME, $file, $parts) !== 1 || str_ends_with($parts[1], '.fixed')) {
            continue;
        }

        $flag = match (true) {
            str_ends_with($parts[1], '.potentially-unsafe') => '--potentially-unsafe',
            str_ends_with($parts[1], '.unsafe') => '--unsafe',
            default => '',
        };
        $core = str_ends_with($parts[1], '.core') ? '--core' : '';
        $groups[$rule . "\0" . $flag . "\0" . $core][] = $directory . '/' . $file;
    }
}

ksort($groups);

$failures = 0;
$count = 0;
foreach ($groups as $key => $cases) {
    [$rule, $flag, $core] = explode("\0", $key);
    $workspace =
        sys_get_temp_dir()
        . '/mago-drupal-fixes-'
        . getmypid()
        . '-'
        . $rule
        . ($flag === '' ? '' : '-' . substr($flag, 2))
        . ($core === '' ? '' : '-core');
    mkdir($workspace, recursive: true);
    file_put_contents($workspace . '/mago.toml', sprintf(
        "version = \"1\"\nphp-version = \"8.1\"\n\n[source]\npaths = [\".\"]\nextensions = [\"php\", \"module\", \"install\", \"inc\", \"theme\"]\n\n[extension-hosts.drupal]\ncommand = [\"php\", \"%s\"%s]\n",
        $worker,
        $core === '' ? '' : ', "--core"',
    ));
    foreach ($cases as $case) {
        copy($case, $workspace . '/' . basename($case));
    }

    $command = [$mago, '--workspace', $workspace, '--config', $workspace . '/mago.toml', 'lint', '--only', 'drupal/' . $rule, '--fix'];
    if ($flag !== '') {
        $command[] = $flag;
    }

    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $workspace);
    $output = $process === false ? '' : stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    $status = $process === false ? -1 : proc_close($process);

    // A run that fails leaves every file as it was, which would pass each
    // case whose fixed text equals its input. Mago exits with 1 when issues
    // are left, which is expected for a case with nothing to fix.
    if (!in_array($status, [0, 1], strict: true) || trim($output) === '' || str_contains($output, ' ERROR ')) {
        fwrite(STDERR, "drupal/{$rule}: mago failed.\n{$output}\n");
        $failures += count($cases);
        $count += count($cases);
        continue;
    }

    foreach ($cases as $case) {
        $count++;
        $expectedFile = preg_replace(CASE_NAME, '$1.fixed.$2', $case);
        $actual = (string) file_get_contents($workspace . '/' . basename($case));
        if ($update) {
            file_put_contents($expectedFile, $actual);
            continue;
        }

        if (!is_file($expectedFile)) {
            fwrite(STDERR, "Missing {$expectedFile}.\n");
            $failures++;
            continue;
        }

        if ($actual === file_get_contents($expectedFile)) {
            continue;
        }

        $failures++;
        $name = substr($case, strlen($root) + 1);
        fwrite(STDERR, "{$name}: the fixed text differs from " . basename($expectedFile) . ".\n--- got\n{$actual}--- mago\n{$output}\n");
    }

    foreach (scandir($workspace) ?: [] as $file) {
        if ($file !== '.' && $file !== '..') {
            unlink($workspace . '/' . $file);
        }
    }

    rmdir($workspace);
}

if ($failures > 0) {
    fwrite(STDERR, "{$failures} of {$count} fix cases failed.\n");
    exit(1);
}

fwrite(STDOUT, "{$count} fix cases hold.\n");
