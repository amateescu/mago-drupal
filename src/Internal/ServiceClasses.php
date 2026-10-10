<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function dirname;
use function file_get_contents;
use function getcwd;
use function glob;
use function is_file;
use function ltrim;
use function preg_match_all;
use function str_replace;
use function str_starts_with;
use function strlen;
use function trim;

/**
 * Reads the classes that a `*.services.yml` file registers as services.
 *
 * Coder's DrupalPractice standard looks for the file in the directory of the
 * checked file and then in each directory above it. It takes the first
 * directory that has one, and the shortest file name there. This class
 * finds the same file.
 *
 * @mago-expect lint:cyclomatic-complexity
 *
 * @internal
 */
final class ServiceClasses
{
    private function __construct() {}

    /**
     * Whether $class, a fully qualified name without a leading backslash,
     * is a service of the code that holds the file at $path.
     *
     * Mago gives the path from the workspace, and starts the worker in the
     * directory of its config file. In a usual setup that is the workspace.
     */
    public static function has(string $path, string $class): bool
    {
        if (!str_starts_with($path, '/')) {
            $directory = getcwd();
            if ($directory === false) {
                return false;
            }

            $path = $directory . '/' . $path;
        }

        $file = self::servicesFile(dirname($path));

        return $file !== null && (self::classes($file)[$class] ?? false);
    }

    /**
     * The services file for a directory: the shortest `*.services.yml` in the
     * nearest directory, at or above it, that has one.
     */
    private static function servicesFile(string $directory): ?string
    {
        // A worker lints many files of one module, so each directory is
        // searched once.
        /** @var array<string, string> $memo */
        static $memo = [];

        $found = $memo[$directory] ?? null;
        if ($found === null) {
            $found = self::shortest($directory);
            $parent = dirname($directory);
            if ($found === '' && $parent !== $directory) {
                $found = self::servicesFile($parent) ?? '';
            }

            $memo[$directory] = $found;
        }

        return $found === '' ? null : $found;
    }

    /**
     * The `*.services.yml` file with the shortest name in a directory, or an
     * empty string when there is none.
     */
    private static function shortest(string $directory): string
    {
        $found = '';
        foreach ((array) glob($directory . '/*.services.yml') as $file) {
            $file = (string) $file;
            if (!is_file($file) || $found !== '' && strlen($file) >= strlen($found)) {
                continue;
            }

            $found = $file;
        }

        return $found;
    }

    /**
     * The classes in a services file: each `class` value, and each service
     * whose name is a class, as in `Drupal\foo\Bar: ~`. The text is read
     * line by line, without a YAML parser. Only the lines under the
     * top-level `services` key count. Other keys, such as `parameters`, can
     * hold class names too.
     *
     * @return array<string, true>
     */
    private static function classes(string $file): array
    {
        /** @var array<string, array<string, true>> $memo */
        static $memo = [];

        $classes = $memo[$file] ?? null;
        if ($classes !== null) {
            return $classes;
        }

        // The block runs to the next line that starts with a key.
        $block = [];
        preg_match('/^services:.*\n((?:(?:[ \t#].*)?\n?)*)/m', (string) file_get_contents($file), $block);
        $matches = [];
        preg_match_all(
            '/^[ \t]+(?:class:[ \t]*([^\s#]+)|([\'"]?[\w\\\\]*\\\\[\w\\\\]+[\'"]?)[ \t]*:)/m',
            $block[1] ?? '',
            $matches,
        );

        $classes = [];
        foreach ([...$matches[1], ...$matches[2]] as $value) {
            // A double-quoted YAML string writes each backslash twice.
            $value = str_replace(search: '\\\\', replace: '\\', subject: trim($value, characters: '\'"'));
            $class = ltrim($value, characters: '\\');
            if ($class !== '') {
                $classes[$class] = true;
            }
        }

        $memo[$file] = $classes;

        return $classes;
    }
}
