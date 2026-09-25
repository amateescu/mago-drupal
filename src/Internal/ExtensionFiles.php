<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function array_filter;
use function array_key_exists;
use function array_pop;
use function array_unique;
use function array_values;
use function count;
use function dirname;
use function filemtime;
use function glob;
use function in_array;
use function is_dir;
use function is_file;
use function is_link;
use function preg_match_all;
use function realpath;
use function scandir;
use function sort;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function substr;
use function time;

use const GLOB_ONLYDIR;
use const SCANDIR_SORT_NONE;

/**
 * Finds extension-owned files under a Drupal root without booting Drupal.
 *
 * The walk is one loop with a directory stack on purpose. It covers all of
 * core/modules on a worker's first lookup, so it stays a single pass with no
 * iterator objects per entry.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class ExtensionFiles
{
    /**
     * Directories Drupal scans for modules, profiles and themes.
     */
    private const EXTENSION_DIRECTORIES = [
        'core/lib',
        'core/modules',
        'core/profiles',
        'core/themes',
        'modules',
        'profiles',
        'themes',
    ];

    /**
     * Directory names that never hold Drupal extensions and are slow to walk.
     */
    private const SKIPPED_DIRECTORIES = ['vendor', 'node_modules', 'files', '.git'];

    /**
     * Seconds within which a directory's listing may still be changing.
     */
    private const SETTLING = 2;

    private function __construct() {}

    /**
     * Walks the root once for every file kind the indexes read.
     *
     * A services file counts only with a sibling `*.info.yml` of the same
     * name; a schema file counts only when its extension directory, three
     * levels up, holds an info file. Both rules leave out scaffold templates
     * and test fixture copies.
     */
    public static function collect(DrupalRoot $root): ExtensionFileSet
    {
        $real = realpath($root->path);
        $base = $real === false ? $root->path : $real;
        [$start, $probed] = self::extensionDirectories($base);
        [$services, $schemas, $extensions, $apiFiles, $directories] = self::walk($base, $start);
        $directories = [...$probed, ...$directories];
        sort($services);
        sort($schemas);
        sort($apiFiles);

        // Core documents most hooks next to the subsystem, under `core/lib`,
        // and the rest at the top of `core`.
        $coreApi = glob($base . '/core/*.api.php');
        if ($coreApi !== false && $coreApi !== []) {
            sort($coreApi);
            $apiFiles = [...$coreApi, ...$apiFiles];
        }

        $core = $base . '/core/core.services.yml';
        if (is_file($core)) {
            $services = [$core, ...$services];
        }

        // Drupal's schema storage reads every `*.yml` in a `config/schema`
        // directory, `ckeditor5.data_types.yml` included.
        $coreSchemas = glob($base . '/core/config/schema/*.yml');
        if ($coreSchemas !== false && $coreSchemas !== []) {
            sort($coreSchemas);
            $schemas = [...$coreSchemas, ...$schemas];
        }

        return new ExtensionFileSet(
            array_values(array_unique($services)),
            array_values(array_unique($schemas)),
            $extensions,
            array_values(array_unique($apiFiles)),
            $directories,
        );
    }

    /**
     * The directories to walk, and every directory probed on the way with its
     * modification time (0 when absent), so a probe that would answer
     * differently later invalidates a cached walk.
     *
     * @return array{list<string>, array<string, int>}
     */
    private static function extensionDirectories(string $root): array
    {
        $candidates = [];
        foreach (self::EXTENSION_DIRECTORIES as $relative) {
            $candidates[] = $root . '/' . $relative;
        }

        $probed = [$root => self::recordedMtime($root), $root . '/sites' => self::recordedMtime($root . '/sites')];
        $sites = glob($root . '/sites/*', GLOB_ONLYDIR);
        foreach ($sites === false ? [] : $sites as $site) {
            $probed[$site] = self::recordedMtime($site);
            $candidates[] = $site . '/modules';
            $candidates[] = $site . '/profiles';
            $candidates[] = $site . '/themes';
        }

        foreach ($candidates as $candidate) {
            $probed[$candidate] = self::recordedMtime($candidate);
        }

        // Core's api.php files and services file sit in `core` itself, and
        // its schema two directories below, none of them walked. A file added
        // to either changes the directory it was added to.
        $probed[$root . '/core'] = self::recordedMtime($root . '/core');
        $probed[$root . '/core/config'] = self::recordedMtime($root . '/core/config');
        $probed[$root . '/core/config/schema'] = self::recordedMtime($root . '/core/config/schema');
        $existing = array_values(array_filter($candidates, is_dir(...)));

        // A bare workspace such as the corpus keeps its extensions at the top
        // level, so the root itself is walked when none of the usual
        // directories exist.
        return [$existing === [] ? [$root] : $existing, $probed];
    }

    /**
     * Every `.php` file under the directories, recursively, with every
     * directory the walk read and its modification time, so a cached listing
     * can be checked without walking again. Directories that do not exist
     * are skipped.
     *
     * @param list<string> $directories
     * @return array{list<string>, array<string, int>}
     */
    public static function phpFileTree(array $directories): array
    {
        $files = [];
        $read = [];
        // A symlink back up the tree would otherwise list the same files again
        // at every level, until the path grows too long.
        $visited = [];
        $stack = array_values(array_filter($directories, is_dir(...)));
        while ($stack !== []) {
            $directory = array_pop($stack);
            $real = realpath($directory);
            if ($real === false || array_key_exists($real, $visited)) {
                continue;
            }

            $visited[$real] = true;
            $entries = scandir($directory, SCANDIR_SORT_NONE);
            $read[$directory] = self::recordedMtime($directory);
            foreach ($entries === false ? [] : $entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $path = $directory . '/' . $entry;
                if (is_dir($path)) {
                    $stack[] = $path;
                    continue;
                }

                if (str_ends_with($entry, '.php')) {
                    $files[] = $path;
                }
            }
        }

        sort($files);

        return [$files, $read];
    }

    /**
     * A directory's modification time, 0 when it does not exist.
     */
    public static function mtime(string $directory): int
    {
        return is_dir($directory) ? (int) filemtime($directory) : 0;
    }

    /**
     * The modification time to store for a directory the walk just listed.
     *
     * A modification time only has second resolution, so a directory written
     * in the last seconds gets -1, which no later reading matches. Without
     * that, a file added between the listing and this call would be missed
     * for as long as the directory stays otherwise untouched.
     */
    public static function recordedMtime(string $directory): int
    {
        $mtime = self::mtime($directory);

        return $mtime >= (time() - self::SETTLING) ? -1 : $mtime;
    }

    private static function inside(string $path, string $root): bool
    {
        return $path === $root || str_starts_with($path, $root . '/');
    }

    /**
     * Walks the directory trees for services, schema and info files.
     *
     * @mago-expect lint:halstead
     *
     * @param string $root Real path of the Drupal root.
     * @param list<string> $directories
     * @return array{list<string>, list<string>, array<string, string>, list<string>, array<string, int>}
     *   Services, schemas, module machine name to directory, api files, and
     *   the directories read with their modification times.
     */
    private static function walk(string $root, array $directories): array
    {
        $services = [];
        $schemas = [];
        $extensions = [];
        $apiFiles = [];
        $read = [];
        $pending = $directories;
        // Symlinked module directories (Composer path repositories) point
        // outside the root and are followed. A link that resolves back inside
        // the root is a loop or an alias of a directory already covered, so it
        // is skipped, and the visited set catches loops through outside paths.
        $visited = [];
        while (($current = array_pop($pending)) !== null) {
            $real = realpath($current);
            if ($real === false || array_key_exists($real, $visited)) {
                continue;
            }

            $visited[$real] = true;
            $entries = scandir($current, SCANDIR_SORT_NONE);
            if ($entries === false) {
                continue;
            }

            $read[$current] = self::recordedMtime($current);

            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $path = $current . '/' . $entry;
                if (is_dir($path) && in_array($entry, self::SKIPPED_DIRECTORIES, strict: true)) {
                    continue;
                }

                if (is_link($path) && self::inside((string) realpath($path), $root)) {
                    continue;
                }

                if (is_dir($path)) {
                    $pending[] = $path;
                    continue;
                }

                if (
                    str_ends_with($entry, '.services.yml')
                    && is_file(substr($path, offset: 0, length: -13) . '.info.yml')
                ) {
                    $services[] = $path;
                    continue;
                }

                if (str_ends_with($entry, '.yml') && self::ownedSchema($path)) {
                    $schemas[] = $path;
                    continue;
                }

                if (str_ends_with($entry, '.api.php')) {
                    $apiFiles[] = $path;
                    continue;
                }

                if (str_ends_with($entry, '.info.yml') && self::isModuleDirectory($current)) {
                    $name = substr($entry, offset: 0, length: -9);
                    // The walk order is not fixed, so a duplicate machine name
                    // resolves to the copy outside a tests directory.
                    if (
                        !array_key_exists($name, $extensions)
                        || self::inTests($extensions[$name]) && !self::inTests($path)
                    ) {
                        $extensions[$name] = $current;
                    }
                }
            }
        }

        return [$services, $schemas, $extensions, $apiFiles, $read];
    }

    /**
     * Whether the extension directory sits under `modules` rather than
     * `themes` or `profiles`; the closest of the three decides, so a module
     * shipped inside a profile counts. A directory under none of them is taken
     * as a module.
     */
    private static function isModuleDirectory(string $directory): bool
    {
        $matches = [];
        if (preg_match_all('#/(modules|themes|profiles)/#', $directory . '/', $matches) === 0) {
            return true;
        }

        return $matches[1][count($matches[1]) - 1] === 'modules';
    }

    private static function inTests(string $path): bool
    {
        return str_contains($path, '/tests/');
    }

    /**
     * Whether a schema file sits in `<extension>/config/schema/` of a real
     * extension.
     */
    private static function ownedSchema(string $path): bool
    {
        $directory = dirname($path);
        if (!str_ends_with($directory, '/config/schema')) {
            return false;
        }

        $infos = glob(dirname($directory, levels: 2) . '/*.info.yml');

        return $infos !== false && $infos !== [];
    }
}
