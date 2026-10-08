<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use FilesystemIterator;
use Mago\Sdk\Analyzer\Codebase;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use UnexpectedValueException;

use function array_key_exists;
use function file_get_contents;
use function in_array;
use function preg_match_all;
use function realpath;
use function str_starts_with;
use function strlen;
use function strtolower;

/**
 * Tells whether a function named after a module exists anywhere in it.
 *
 * A callback naming a missing function is only reported when the function
 * would be in the codebase if it existed. That holds for a function named
 * `<module>_…` or `_<module>_…` after the module the code is written in:
 * such a function belongs in that module's files. Those files are read off
 * disk, so a function in a file the run leaves out still counts.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class ModuleFunctions
{
    private const DECLARATION = '/\bfunction\s++&?\s*+([A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)\s*+\(/';

    /**
     * The directory that holds no function a module declares besides the
     * ones the extension scan skips: `src`, where PSR-4 classes live.
     */
    private const CLASSES = 'src';

    /**
     * Lowercased names of the functions each module directory declares,
     * read again for each analysis, since a watch session edits the files.
     *
     * @var AnalysisMemo<array<string, true>>|null
     */
    private static ?AnalysisMemo $declared = null;

    private function __construct() {}

    /**
     * The module the file belongs to and its directory, when $function is
     * named after it. The innermost module directory holding the file wins,
     * so a submodule owns its own files. A function that another module's
     * longer name also prefixes, such as `foo_bar_submit()` written in `foo`
     * when `foo_bar` exists, belongs to that module, so null comes back.
     *
     * @param array<string, string> $modules Module machine name to directory.
     * @return array{string, string}|null
     */
    public static function owner(array $modules, string $file, string $function): ?array
    {
        $path = realpath($file);
        if ($path === false) {
            return null;
        }

        $lower = strtolower($function);
        $found = null;
        $named = null;
        foreach ($modules as $name => $directory) {
            if (self::namedAfter($lower, $name) && ($named === null || strlen($name) > strlen($named))) {
                $named = $name;
            }

            if (
                !str_starts_with($path, $directory . '/')
                || $found !== null && strlen($directory) <= strlen($found[1])
            ) {
                continue;
            }

            $found = [$name, $directory];
        }

        return $found !== null && $named === $found[0] ? $found : null;
    }

    /**
     * Whether the lowercased function name starts with `<module>_` or
     * `_<module>_`.
     */
    private static function namedAfter(string $function, string $module): bool
    {
        return str_starts_with($function, $module . '_') || str_starts_with($function, '_' . $module . '_');
    }

    /**
     * Whether a file under the directory declares the function. Procedural
     * files and `.php` files outside `src` are read.
     */
    public static function declares(Codebase $codebase, string $directory, string $function): bool
    {
        self::$declared ??= new AnalysisMemo();
        $declared = self::$declared->get($codebase, $directory, static fn(): array => self::names($directory));

        return array_key_exists(strtolower($function), $declared);
    }

    /**
     * The lowercased names of the functions and methods declared in the
     * files under the directory that can declare functions.
     *
     * @return array<string, true>
     */
    public static function names(string $directory): array
    {
        $names = [];
        foreach (self::files($directory) as $path) {
            $names += self::declared((string) file_get_contents($path));
        }

        return $names;
    }

    /**
     * The files under the directory that can declare functions.
     *
     * @return list<string>
     */
    private static function files(string $directory): array
    {
        $paths = [];
        try {
            $files = new RecursiveIteratorIterator(
                new RecursiveCallbackFilterIterator(
                    new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
                    self::descends(...),
                ),
            );
            foreach ($files as $file) {
                if (!$file instanceof SplFileInfo || !self::readable(strtolower($file->getExtension()))) {
                    continue;
                }

                $paths[] = $file->getPathname();
            }
        } catch (UnexpectedValueException) {
            return $paths;
        }

        return $paths;
    }

    /**
     * Whether the walk goes into an entry: any file, and a directory other
     * than the skipped ones.
     */
    private static function descends(SplFileInfo|string $file): bool
    {
        if (!$file instanceof SplFileInfo || !$file->isDir()) {
            return true;
        }

        $name = $file->getFilename();

        return $name !== self::CLASSES && !in_array($name, ExtensionFiles::SKIPPED_DIRECTORIES, strict: true);
    }

    /**
     * Whether a file with the extension can declare functions: a procedural
     * file or a `.php` file.
     */
    private static function readable(string $extension): bool
    {
        return $extension === 'php' || in_array($extension, DrupalFile::PROCEDURAL_EXTENSIONS, strict: true);
    }

    /**
     * The lowercased names of the functions and methods the text declares.
     *
     * @return array<string, true>
     */
    private static function declared(string $contents): array
    {
        $matches = [];
        preg_match_all(self::DECLARATION, $contents, $matches);
        $names = [];
        foreach ($matches[1] as $name) {
            $names[strtolower($name)] = true;
        }

        return $names;
    }
}
