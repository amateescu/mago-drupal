<?php

/**
 * Checks that every corpus expectation holds on the line it is written above.
 *
 * Mago matches an own-line `@mago-expect` inside a function body to any later
 * issue with the same code in the file. A fixture whose issue disappears can
 * then absorb an issue that appeared a few lines further down, and the corpus
 * run stays green. This script runs the corpus again on a copy where every
 * `@mago-expect` is renamed, so Mago applies none of them, and does the
 * matching itself with a stricter rule.
 *
 * A pragma covers the lines from itself to the end of the statement, or the
 * declaration header, that starts on the first line below it that is not
 * blank, a comment or an attribute. Every issue needs a pragma of its code
 * covering the line it starts on, and every pragma needs exactly one issue,
 * or the count it names in `(N)`.
 *
 * Usage: php tests/CorpusPins.php <mago binary>
 */

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\Shape;
use FilesystemIterator;
use PhpToken;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

use function copy;
use function count;
use function dirname;
use function explode;
use function file;
use function file_get_contents;
use function file_put_contents;
use function fwrite;
use function getmypid;
use function implode;
use function in_array;
use function is_dir;
use function json_decode;
use function mkdir;
use function preg_match;
use function proc_close;
use function proc_open;
use function putenv;
use function rmdir;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;
use function sys_get_temp_dir;
use function trim;
use function unlink;
use function usort;

use const FILE_IGNORE_NEW_LINES;
use const FILE_SKIP_EMPTY_LINES;
use const JSON_THROW_ON_ERROR;
use const STDERR;
use const STDOUT;
use const T_CURLY_OPEN;
use const T_DOLLAR_OPEN_CURLY_BRACES;

/**
 * Runs the check; see the file docblock.
 *
 * @phpstan-type Pragma array{file: string, line: int, end: int, category: string, code: string, count: int}
 * @phpstan-type Found array{file: string, line: int, category: string, code: string}
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class CorpusPins
{
    /**
     * An own-line pragma with one code and an optional count.
     */
    private const PRAGMA = '/^\s*\/\/\s*@mago-expect\s+(lint|analysis):([\w\/-]+)(?:\((\d+)\))?\s*$/';

    /**
     * Mago arguments that switch the report to JSON on stdout.
     */
    private const JSON = ['--reporting-format', 'json'];

    private function __construct() {}

    /**
     * Returns the process exit code.
     *
     * @param list<string> $arguments
     */
    public static function main(array $arguments): int
    {
        $mago = $arguments[1] ?? null;
        if ($mago === null) {
            fwrite(STDERR, data: "Usage: php tests/CorpusPins.php <mago binary>\n");

            return 2;
        }

        $corpus = __DIR__ . '/corpus';
        $copy = sys_get_temp_dir() . '/mago-drupal-pins-' . (string) getmypid();
        try {
            self::copyTree($corpus, $copy);
            self::pointAtWorker($copy . '/mago.toml', dirname(__DIR__) . '/resources/worker.php');
            $pragmas = self::neutralize($copy . '/src');
            $rules = file($corpus . '/expected-rules.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $codes = implode(',', $rules === false ? [] : $rules);

            // The copy lives under a new path each run, so a cache entry keyed
            // on its root would never be read again.
            putenv('MAGO_DRUPAL_CACHE=0');
            $found = [
                ...self::issues([$mago, '--workspace', $copy, 'lint', '--only', $codes, ...self::JSON], 'lint'),
                ...self::issues([$mago, '--workspace', $copy, 'analyze', ...self::JSON], 'analysis'),
            ];
        } finally {
            self::removeTree($copy);
        }

        return self::report($pragmas, $found);
    }

    /**
     * Matches issues to pragmas and prints what is left on either side.
     *
     * @param list<Pragma> $pragmas
     * @param list<Found> $found
     */
    private static function report(array $pragmas, array $found): int
    {
        [$stray, $left] = self::assign($pragmas, $found);
        foreach ($stray as $issue) {
            fwrite(
                STDERR,
                "{$issue['file']}:{$issue['line']}: no pragma above for {$issue['category']}:{$issue['code']}\n",
            );
        }

        $missing = false;
        foreach ($pragmas as $index => $pragma) {
            if ($left[$index] === 0) {
                continue;
            }

            $missing = true;
            $lines = (string) $pragma['line'] . '-' . (string) $pragma['end'];
            fwrite(
                STDERR,
                "{$pragma['file']}:{$pragma['line']}: {$pragma['category']}:{$pragma['code']} is missing {$left[$index]} issue(s) on lines {$lines}\n",
            );
        }

        if ($stray !== [] || $missing) {
            return 1;
        }

        fwrite(STDOUT, count($pragmas) . " expectations pinned to their lines.\n");

        return 0;
    }

    /**
     * Gives each issue to the closest pragma above it that covers it and has
     * room left. Returns the issues no pragma took, and what each pragma
     * still misses.
     *
     * @param list<Pragma> $pragmas
     * @param list<Found> $found
     * @return array{list<Found>, array<int, int>}
     */
    private static function assign(array $pragmas, array $found): array
    {
        usort($found, static fn(array $a, array $b): int => [$a['file'], $a['line']] <=> [$b['file'], $b['line']]);
        $left = [];
        foreach ($pragmas as $index => $pragma) {
            $left[$index] = $pragma['count'];
        }

        $stray = [];
        foreach ($found as $issue) {
            $best = null;
            foreach ($pragmas as $index => $pragma) {
                if ($left[$index] === 0 || !self::covers($pragma, $issue)) {
                    continue;
                }

                // Like Mago, the closest pragma above the issue wins.
                if ($best === null || $pragma['line'] > $pragmas[$best]['line']) {
                    $best = $index;
                }
            }

            if ($best === null) {
                $stray[] = $issue;
                continue;
            }

            $left[$best]--;
        }

        return [$stray, $left];
    }

    /**
     * Whether the issue starts inside the pragma's lines and has its code.
     *
     * @param Pragma $pragma
     * @param Found $issue
     */
    private static function covers(array $pragma, array $issue): bool
    {
        return (
            $pragma['file'] === $issue['file']
            && $pragma['category'] === $issue['category']
            && $pragma['code'] === $issue['code']
            && $pragma['line'] < $issue['line']
            && $issue['line'] <= $pragma['end']
        );
    }

    /**
     * Reads the pragmas under a directory and renames them in place, so Mago
     * no longer applies them. The new name still starts with `@mago-`, which
     * the comment rules treat as a directive, so it adds no issue of its own.
     *
     * @return list<Pragma>
     */
    private static function neutralize(string $directory): array
    {
        $pragmas = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $directory,
            FilesystemIterator::SKIP_DOTS,
        ));
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            $path = $file->getPathname();
            $source = file_get_contents($path);
            if ($source === false || !str_contains($source, '@mago-expect')) {
                continue;
            }

            $relative = 'src/' . substr($path, offset: strlen($directory) + 1);
            $lines = explode("\n", $source);
            $tokens = PhpToken::tokenize($source);
            foreach ($lines as $index => $line) {
                if (!str_contains($line, '@mago-expect')) {
                    continue;
                }

                $matches = [];
                if (preg_match(self::PRAGMA, $line, $matches) !== 1) {
                    throw new RuntimeException(
                        "{$relative}:" . (string) ($index + 1) . ': only own-line pragmas with one code are supported.',
                    );
                }

                $target = self::target($lines, $index + 1);
                $pragmas[] = [
                    'file' => $relative,
                    'line' => $index + 1,
                    'end' => $target === null ? $index + 1 : self::statementEnd($tokens, $target),
                    'category' => $matches[1],
                    'code' => $matches[2],
                    'count' => ($matches[3] ?? '') === '' ? 1 : (int) $matches[3],
                ];
            }

            file_put_contents($path, str_replace('@mago-expect', replace: '@mago-pinned', subject: $source));
        }

        return $pragmas;
    }

    /**
     * The first line number from `$from` (0-based) on that is not blank, a
     * comment or an attribute, 1-based.
     *
     * @param list<string> $lines
     */
    private static function target(array $lines, int $from): ?int
    {
        for ($index = $from; $index < count($lines); $index++) {
            $line = trim($lines[$index]);
            $comment =
                $line === ''
                || str_starts_with($line, '//')
                || str_starts_with($line, '/*')
                || str_starts_with($line, '*')
                || str_starts_with($line, '#');
            if (!$comment) {
                return $index + 1;
            }
        }

        return null;
    }

    /**
     * The line of the `;` or `{` that ends the statement or declaration header
     * starting on the given line, outside any bracket.
     *
     * @param list<PhpToken> $tokens
     */
    private static function statementEnd(array $tokens, int $start): int
    {
        $depth = 0;
        $end = $start;
        foreach ($tokens as $token) {
            if ($token->line < $start || $token->isIgnorable()) {
                continue;
            }

            $end = $token->line;
            // `{$x}` and `${x}` open inside a string and close with a plain `}`.
            $interpolation = $token->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES]);
            if ($depth === 0 && !$interpolation && ($token->text === ';' || $token->text === '{')) {
                return $end;
            }

            // The block around the statement closes before the statement ends.
            if ($depth === 0 && $token->text === '}') {
                return $start;
            }

            $depth += match (true) {
                $interpolation, in_array($token->text, ['(', '[', '{'], strict: true) => 1,
                in_array($token->text, [')', ']', '}'], strict: true) => -1,
                default => 0,
            };
        }

        return $end;
    }

    /**
     * Runs Mago and reads the issues it reports, with 1-based lines.
     *
     * @param list<string> $command
     * @return list<Found>
     */
    private static function issues(array $command, string $category): array
    {
        // A file instead of a pipe, so a large report never blocks Mago.
        $output = sys_get_temp_dir() . '/mago-drupal-pins-' . (string) getmypid() . '.json';
        $pipes = [];
        $process = proc_open($command, [1 => ['file', $output, 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        if ($process === false) {
            throw new RuntimeException('Could not start ' . implode(' ', $command));
        }

        proc_close($process);
        $json = (string) file_get_contents($output);
        unlink($output);

        $found = [];
        /** @var mixed $issue */
        foreach (Shape::arrayAt(
            json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR),
            'issues',
        ) as $issue) {
            $found[] = self::found($issue, $category);
        }

        return $found;
    }

    /**
     * Reads one issue of Mago's JSON report: its code and where its primary
     * annotation starts.
     *
     * @return Found
     */
    private static function found(mixed $issue, string $category): array
    {
        $annotations = Shape::arrayAt($issue, 'annotations');
        $primaryKey = 0;
        /** @var mixed $annotation */
        foreach ($annotations as $key => $annotation) {
            if (Shape::stringAt($annotation, 'kind') !== 'Primary') {
                continue;
            }

            $primaryKey = $key;
            break;
        }

        /** @var mixed $primary */
        $primary = $annotations[$primaryKey] ?? null;

        $code = Shape::stringAt($issue, 'code');
        $file = Shape::stringAt($primary, 'span', 'file_id', 'name');
        $line = Shape::int(Shape::arrayAt($primary, 'span', 'start')['line'] ?? null);
        if ($code === null || $file === null || $line === null) {
            throw new RuntimeException('Unexpected issue shape in Mago output.');
        }

        // Mago reports 0-based lines.
        return ['file' => $file, 'line' => $line + 1, 'category' => $category, 'code' => $code];
    }

    /**
     * Makes the copy's worker command absolute, since the relative one only
     * works from `tests/corpus`.
     */
    private static function pointAtWorker(string $config, string $worker): void
    {
        $contents = (string) file_get_contents($config);
        $relative = '"../../resources/worker.php"';
        if (!str_contains($contents, $relative)) {
            throw new RuntimeException("The corpus config no longer names {$relative}.");
        }

        file_put_contents($config, str_replace($relative, '"' . $worker . '"', $contents));
    }

    /**
     * Copies a directory tree.
     */
    private static function copyTree(string $from, string $to): void
    {
        mkdir($to, recursive: true);
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        /** @var SplFileInfo $entry */
        foreach ($entries as $entry) {
            $target = $to . substr($entry->getPathname(), offset: strlen($from));
            if ($entry->isDir()) {
                mkdir($target);
                continue;
            }

            copy($entry->getPathname(), $target);
        }
    }

    /**
     * Removes a directory tree.
     */
    private static function removeTree(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var SplFileInfo $entry */
        foreach ($entries as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());
                continue;
            }

            unlink($entry->getPathname());
        }

        rmdir($directory);
    }
}

require dirname(__DIR__) . '/vendor/autoload.php';

exit(CorpusPins::main($argv));
