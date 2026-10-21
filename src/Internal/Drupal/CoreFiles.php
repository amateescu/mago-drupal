<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Drupal;

use function basename;
use function dirname;
use function is_file;

/**
 * Recognises the files of Drupal core itself.
 *
 * Core's `phpcs.xml.dist` runs two checks in some directories only. A rule's
 * `exclude` setting can't say that, so the two rules check the path
 * themselves. Core also looks up only its own services, so every service id is
 * checked in its files. A file is core's when one of its directories is named
 * `core` and holds `lib/Drupal.php`.
 *
 * @internal
 */
final class CoreFiles
{
    private function __construct() {}

    /**
     * Whether the file sits under the `core` directory of a Drupal root. Mago
     * names a file by its path from the workspace, and starts the worker there.
     */
    public static function contains(string $path): bool
    {
        $directory = NearestFile::directoryOf($path);

        return $directory !== null && self::inCore($directory);
    }

    /**
     * Whether $directory is a Drupal `core` directory or sits under one.
     */
    private static function inCore(string $directory): bool
    {
        // A worker checks many files of the same directories, so each one is
        // looked at once.
        /** @var array<string, bool> $memo */
        static $memo = [];

        $known = $memo[$directory] ?? null;
        if ($known !== null) {
            return $known;
        }

        $parent = dirname($directory);
        $found =
            basename($directory) === 'core' && is_file($directory . '/lib/Drupal.php')
            || $parent !== $directory && self::inCore($parent);
        $memo[$directory] = $found;

        return $found;
    }
}
