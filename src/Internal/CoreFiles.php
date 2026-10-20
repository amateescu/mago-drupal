<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function basename;
use function dirname;
use function is_file;

/**
 * Recognises the files of Drupal core itself.
 *
 * Core's `phpcs.xml.dist` turns off some of Drupal's checks and runs others in
 * some directories only, and some analyzer checks are about contrib and custom
 * code. A file is core's when one of its directories is named `core` and holds
 * `lib/Drupal.php`. One run then checks a site's modules by Drupal's standard
 * and its `core/` by core's.
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
