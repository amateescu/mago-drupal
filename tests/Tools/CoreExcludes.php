<?php

/**
 * Checks the files that drupal-core.mago.toml leaves out against a core checkout.
 *
 * Core's phpcs doesn't check the paths in the top-level `<exclude-pattern>`
 * entries of `core/phpcs.xml.dist`, or a file with a `// phpcs:ignoreFile`
 * comment. Mago doesn't read either one, so the preset lists them as excludes.
 * This script works out that list from the checkout and compares it with the
 * excludes of the preset.
 *
 * A marked file is listed by the topmost directory whose checked files are all
 * marked, or by itself. A marker inside a string, such as the heredoc of code
 * that DbDumpCommand writes, isn't a comment, and phpcs checks that file.
 *
 * A file that the preset leaves out of both the linter and the analyzer is
 * broken on purpose, and Mago reports an error for it that no rule setting
 * turns off. phpcs checks it, so it isn't compared.
 *
 * Usage: php tests/Tools/CoreExcludes.php <mago binary> <core checkout>
 *
 * The checkout is the directory that holds `core/`.
 */

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\Type\Shape;
use FilesystemIterator;
use PhpToken;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SimpleXMLElement;
use SplFileInfo;

use function array_diff;
use function array_keys;
use function array_map;
use function array_unique;
use function array_values;
use function count;
use function dirname;
use function file_get_contents;
use function fwrite;
use function implode;
use function in_array;
use function is_array;
use function is_dir;
use function is_string;
use function json_decode;
use function preg_match;
use function proc_close;
use function proc_open;
use function rtrim;
use function sort;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function stream_get_contents;
use function strlen;
use function substr;

use const JSON_THROW_ON_ERROR;
use const STDERR;
use const STDOUT;
use const T_COMMENT;
use const T_DOC_COMMENT;

/**
 * Runs the check; see the file docblock.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class CoreExcludes
{
    /**
     * The extensions that core's phpcs.xml.dist checks, without yml.
     */
    private const EXTENSIONS = ['engine', 'inc', 'install', 'module', 'php', 'profile', 'test', 'theme'];

    /**
     * A comment line that tells phpcs to skip the file.
     */
    private const MARKER = '/^\s*(?:\/\/|#|\/\*\*?|\*)\s*(?:phpcs:ignoreFile|@codingStandardsIgnoreFile)\b/m';

    /**
     * Directories that hold front-end packages, which the preset leaves out by glob.
     */
    private const SKIPPED = ['node_modules', 'bower_components'];

    private function __construct() {}

    /**
     * Returns the process exit code.
     *
     * @param list<string> $arguments
     */
    public static function main(array $arguments): int
    {
        $mago = $arguments[1] ?? null;
        $checkout = $arguments[2] ?? null;
        if ($mago === null || $checkout === null || !is_dir($checkout . '/core')) {
            fwrite(STDERR, data: "Usage: php tests/Tools/CoreExcludes.php <mago binary> <core checkout>\n");

            return 2;
        }

        $checkout = rtrim($checkout, characters: '/');
        $expected = [...self::phpcsExcludes($checkout), ...self::markedPaths($checkout)];
        $expected = array_values(array_unique(array_map(self::normalize(...), $expected)));
        sort($expected);

        $broken = self::coreEntries(self::excludes($mago, 'analyzer'));
        $linter = array_values(array_diff(self::coreEntries(self::excludes($mago, 'linter')), $broken));
        $formatter = self::coreEntries(self::excludes($mago, 'formatter'));
        $actual = array_values(array_unique([...$linter, ...self::coreEntries(self::excludes($mago, 'source'))]));

        $missing = array_values(array_diff($expected, $actual));
        $stale = array_values(array_diff($actual, $expected));
        $unformatted = array_values(array_diff($linter, $formatter));
        if ($missing === [] && $stale === [] && $unformatted === []) {
            fwrite(STDOUT, data: sprintf("%d excludes match %s.\n", count($expected), $checkout));

            return 0;
        }

        self::listPaths('Not left out by the preset:', $missing);
        self::listPaths('Left out by the preset, but checked by phpcs:', $stale);
        self::listPaths('Left out of the linter, but not the formatter:', $unformatted);

        return 1;
    }

    /**
     * Returns the top-level exclude patterns of core/phpcs.xml.dist that name a path.
     *
     * The patterns are relative to core/, as phpcs runs there. A `*` at the
     * start matches any directory, and those patterns become globs in the
     * preset, which aren't compared.
     *
     * @return list<string>
     */
    private static function phpcsExcludes(string $checkout): array
    {
        $xml = new SimpleXMLElement((string) file_get_contents($checkout . '/core/phpcs.xml.dist'));
        $paths = [];
        $patterns = $xml->xpath('/ruleset/exclude-pattern');
        foreach (is_array($patterns) ? $patterns : [] as $pattern) {
            $path = (string) $pattern;
            if (str_starts_with($path, '*')) {
                continue;
            }

            $path = str_starts_with($path, './') ? substr($path, offset: 2) : $path;
            $path = str_ends_with($path, '*') ? substr($path, offset: 0, length: -1) : $path;
            $paths[] = str_starts_with($path, 'core/') ? $path : 'core/' . $path;
        }

        return $paths;
    }

    /**
     * Returns the files with a phpcs:ignoreFile comment, grouped by directory where possible.
     *
     * @return list<string>
     */
    private static function markedPaths(string $checkout): array
    {
        $checked = [];
        $marked = [];
        foreach (['core', 'composer'] as $top) {
            if (!is_dir($checkout . '/' . $top)) {
                continue;
            }

            foreach (self::files($checkout . '/' . $top) as $file) {
                $path = substr($file->getPathname(), offset: strlen($checkout) + 1);
                $checked[] = $path;
                if (self::isMarked((string) file_get_contents($file->getPathname()))) {
                    $marked[$path] = true;
                }
            }
        }

        $unmarked = self::unmarkedCounts($checked, $marked);
        $paths = [];
        foreach (array_keys($marked) as $path) {
            $top = $path;
            for ($directory = dirname($path); str_contains($directory, '/'); $directory = dirname($directory)) {
                if (($unmarked[$directory] ?? 0) !== 0) {
                    break;
                }

                $top = $directory . '/';
            }

            $paths[$top] = true;
        }

        return array_keys($paths);
    }

    /**
     * Returns the number of checked files without a marker under each directory.
     *
     * @param list<string> $checked
     * @param array<string, true> $marked
     * @return array<string, int>
     */
    private static function unmarkedCounts(array $checked, array $marked): array
    {
        $counts = [];
        foreach ($checked as $path) {
            if ($marked[$path] ?? false) {
                continue;
            }

            for ($directory = dirname($path); str_contains($directory, '/'); $directory = dirname($directory)) {
                $counts[$directory] = ($counts[$directory] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * Returns the files with an extension that phpcs checks.
     *
     * @return iterable<SplFileInfo>
     */
    private static function files(string $directory): iterable
    {
        $skip = static fn(SplFileInfo|string $entry): bool => !$entry instanceof SplFileInfo
        || !$entry->isDir()
        || !in_array($entry->getFilename(), self::SKIPPED, strict: true);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
                $skip,
            ),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || !in_array($file->getExtension(), self::EXTENSIONS, strict: true)) {
                continue;
            }

            yield $file;
        }
    }

    /**
     * Tells whether a comment in the code tells phpcs to skip the file.
     */
    private static function isMarked(string $code): bool
    {
        if (!str_contains($code, 'gnoreFile')) {
            return false;
        }

        foreach (PhpToken::tokenize($code) as $token) {
            if ($token->is([T_COMMENT, T_DOC_COMMENT]) && preg_match(self::MARKER, $token->text) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the excludes of one section of the preset, as Mago merges them.
     *
     * @return list<string>
     */
    private static function excludes(string $mago, string $section): array
    {
        $config = dirname(__DIR__, levels: 2) . '/drupal-core.mago.toml';
        $pipes = [];
        $process = proc_open([$mago, '--config', $config, 'config', '--show', $section], [1 => ['pipe', 'w']], $pipes);
        if ($process === false) {
            throw new RuntimeException('Could not start ' . $mago);
        }

        $stdout = $pipes[1] ?? null;
        $json = $stdout === null ? '' : (string) stream_get_contents($stdout);
        if (proc_close($process) !== 0) {
            throw new RuntimeException(sprintf('mago config --show %s failed.', $section));
        }

        $excludes = [];
        /** @var mixed $exclude */
        foreach (Shape::arrayAt(
            json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR),
            'excludes',
        ) as $exclude) {
            if (!is_string($exclude)) {
                continue;
            }

            $excludes[] = $exclude;
        }

        return $excludes;
    }

    /**
     * Returns the excludes that name a path under core/, normalized.
     *
     * @param list<string> $excludes
     * @return list<string>
     */
    private static function coreEntries(array $excludes): array
    {
        $entries = [];
        foreach ($excludes as $exclude) {
            if (!str_starts_with($exclude, 'core/')) {
                continue;
            }

            $entries[] = self::normalize($exclude);
        }

        return $entries;
    }

    /**
     * Drops a trailing slash, so a directory compares the same with and without one.
     */
    private static function normalize(string $path): string
    {
        return rtrim($path, characters: '/');
    }

    /**
     * Writes a heading and the paths under it, when there are any.
     *
     * @param list<string> $paths
     */
    private static function listPaths(string $heading, array $paths): void
    {
        if ($paths === []) {
            return;
        }

        fwrite(STDOUT, data: $heading . "\n  " . implode("\n  ", $paths) . "\n");
    }
}

require dirname(__DIR__, levels: 2) . '/vendor/autoload.php';

exit(CoreExcludes::main($argv));
