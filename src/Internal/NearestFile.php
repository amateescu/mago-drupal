<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function dirname;
use function getcwd;
use function glob;
use function implode;
use function is_file;
use function str_starts_with;
use function strlen;

/**
 * Finds a file that Coder's DrupalPractice standard reads next to the
 * checked file, such as the module's `*.info.yml` or `*.services.yml`.
 *
 * Coder looks in the directory of the checked file and then in each
 * directory above it. It takes the first directory that has a match, and
 * the shortest file name there.
 *
 * @internal
 */
final class NearestFile
{
    private function __construct() {}

    /**
     * The absolute directory of a file that Mago names by its path from the
     * workspace, or null when the worker's directory is unknown.
     *
     * Mago starts the worker in the directory of its config file. In a usual
     * setup that is the workspace.
     */
    public static function directoryOf(string $path): ?string
    {
        if (!str_starts_with($path, '/')) {
            $directory = getcwd();
            if ($directory === false) {
                return null;
            }

            $path = $directory . '/' . $path;
        }

        return dirname($path);
    }

    /**
     * The nearest file at or above $directory that matches one of $patterns,
     * such as `*.info.yml`. A later pattern counts only in a directory where
     * the earlier ones match nothing.
     *
     * @param list<string> $patterns
     */
    public static function find(string $directory, array $patterns): ?string
    {
        // A worker lints many files of one module, so each directory is
        // searched once.
        /** @var array<string, string> $memo */
        static $memo = [];

        $key = $directory . "\0" . implode("\0", $patterns);
        $found = $memo[$key] ?? null;
        if ($found === null) {
            $found = self::shortest($directory, $patterns);
            $parent = dirname($directory);
            if ($found === '' && $parent !== $directory) {
                $found = self::find($parent, $patterns) ?? '';
            }

            $memo[$key] = $found;
        }

        return $found === '' ? null : $found;
    }

    /**
     * The file with the shortest name in a directory that matches the first
     * pattern with a match, or an empty string when none does.
     *
     * @param list<string> $patterns
     */
    private static function shortest(string $directory, array $patterns): string
    {
        foreach ($patterns as $pattern) {
            $found = '';
            foreach ((array) glob($directory . '/' . $pattern) as $file) {
                $file = (string) $file;
                if (!is_file($file) || $found !== '' && strlen($file) >= strlen($found)) {
                    continue;
                }

                $found = $file;
            }

            if ($found !== '') {
                return $found;
            }
        }

        return '';
    }
}
