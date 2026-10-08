<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function file_get_contents;
use function is_file;
use function preg_match;
use function strlen;
use function substr;

/**
 * Reads the info file next to a services file, which says whether its module
 * is required and so always enabled.
 *
 * @internal
 */
final class ServiceModuleInfo
{
    /**
     * A top-level `required: true` in an info file.
     */
    private const REQUIRED = '/^required:[ \t]*true[ \t]*(?:#.*)?$/mi';

    private function __construct() {}

    /**
     * Whether the module owning the services file is marked required.
     */
    public static function required(string $servicesFile): bool
    {
        $info = self::infoFile($servicesFile);
        $text = is_file($info) ? file_get_contents($info) : false;

        return $text !== false && preg_match(self::REQUIRED, $text) === 1;
    }

    /**
     * The info files next to the services files, for a cache key that
     * covers what `required()` reads.
     *
     * @param list<string> $servicesFiles
     * @return list<string>
     */
    public static function infoFiles(array $servicesFiles): array
    {
        $files = [];
        foreach ($servicesFiles as $servicesFile) {
            $info = self::infoFile($servicesFile);
            if (is_file($info)) {
                $files[] = $info;
            }
        }

        return $files;
    }

    /**
     * The `<name>.info.yml` next to `<name>.services.yml`.
     */
    private static function infoFile(string $servicesFile): string
    {
        return substr($servicesFile, offset: 0, length: -strlen('.services.yml')) . '.info.yml';
    }
}
