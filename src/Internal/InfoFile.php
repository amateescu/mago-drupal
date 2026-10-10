<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function basename;
use function file_get_contents;
use function preg_match;
use function preg_replace;
use function str_ends_with;

/**
 * Reads what Coder's DrupalPractice standard takes from the info file of the
 * module, theme or profile that holds a file: its machine name and its
 * Drupal version.
 *
 * The info file is the nearest `*.info.yml` file, or Drupal 7 `*.info` file,
 * in the directory of the file or one above.
 *
 * @internal
 */
final class InfoFile
{
    private function __construct() {}

    /**
     * The machine name for the file at $path, as Mago names it, or null
     * when the file has none.
     *
     * A `.module`, `.install`, `.profile` or `.theme` file is named after
     * it. Any other file, such as an `.inc` file or a class in `src/`, takes
     * the name of its info file.
     */
    public static function moduleName(string $path): ?string
    {
        $drupalFile = DrupalFile::fromPath($path);
        if ($drupalFile->isNamedByFile()) {
            return $drupalFile->name;
        }

        $info = self::nearest($path);

        return $info === null
            ? null
            : (string) preg_replace('/\.info(?:\.yml)?$/', replacement: '', subject: basename($info));
    }

    /**
     * The major Drupal version that the file at $path is written for.
     *
     * A file with no info file or with an `*.info.yml` file is for Drupal 8
     * or later, and counts as 8. A Drupal 6 or 7 `*.info` file names its
     * version in a `core = 7.x` line, and one without that line counts as 7.
     */
    public static function coreVersion(string $path): int
    {
        $info = self::nearest($path);
        if ($info === null || str_ends_with($info, '.yml')) {
            return 8;
        }

        /** @var array<string, int> $memo */
        static $memo = [];
        $version = $memo[$info] ?? null;
        if ($version === null) {
            $match = [];
            $version = preg_match('/^\s*core\s*=\s*["\']?(\d)/m', (string) file_get_contents($info), $match) === 1
                ? (int) $match[1]
                : 7;
            $memo[$info] = $version;
        }

        return $version;
    }

    /**
     * The nearest info file of the file at $path, or null.
     */
    private static function nearest(string $path): ?string
    {
        $directory = NearestFile::directoryOf($path);

        return $directory === null ? null : NearestFile::find($directory, ['*.info.yml', '*.info']);
    }
}
