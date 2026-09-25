<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function array_key_exists;
use function file_get_contents;
use function is_file;
use function preg_match_all;

/**
 * Raw service definitions from several sources, merged into one set.
 *
 * @internal
 *
 * @phpstan-import-type Definition from ServiceYaml
 */
final class ServiceDefinitions
{
    /**
     * A registration call with a literal id as its first argument.
     */
    private const REGISTRATION = '/->\s*(?:register|setDefinition|setAlias)\s*\(\s*([\'"])([^\'"]+)\1/';

    private function __construct() {}

    /**
     * Merges raw definitions, later ones winning, except that an entry
     * without a class never erases one that names its class.
     *
     * @param array<non-empty-string, Definition> $base
     * @param array<non-empty-string, Definition> $extra
     * @return array<non-empty-string, Definition>
     */
    public static function merge(array $base, array $extra): array
    {
        foreach ($extra as $id => $definition) {
            if ($definition === [] && array_key_exists($id, $base)) {
                continue;
            }

            $base[$id] = $definition;
        }

        return $base;
    }

    /**
     * The ids registered in provider files on disk, as definitions without a
     * class; a match by text, since no snapshot exists for an include.
     *
     * @param list<string> $files
     * @return array<non-empty-string, Definition>
     */
    public static function idsInFiles(array $files): array
    {
        $definitions = [];
        foreach ($files as $file) {
            $source = is_file($file) ? file_get_contents($file) : false;
            $matches = [];
            if ($source === false || preg_match_all(self::REGISTRATION, $source, $matches) === 0) {
                continue;
            }

            foreach ($matches[2] as $match) {
                $id = Shape::nonEmptyString($match);
                if ($id !== null) {
                    $definitions[$id] = [];
                }
            }
        }

        return $definitions;
    }
}
