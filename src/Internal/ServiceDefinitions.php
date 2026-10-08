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

    /**
     * A container builder call with a literal id that can replace or change
     * a definition, its arguments included.
     */
    private const ALTERATION = '/->\s*(?:register|autowire|setDefinition|getDefinition|findDefinition|removeDefinition)\s*\(\s*([\'"])([^\'"]+)\1/';

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
        foreach (self::literalIds($files, self::REGISTRATION) as $id => $_) {
            $definitions[$id] = [];
        }

        return $definitions;
    }

    /**
     * The ids that provider or compiler pass files on disk register, fetch
     * or remove by a literal, as a match by text.
     *
     * @param list<string> $files
     * @return array<non-empty-string, true>
     */
    public static function alteredIdsInFiles(array $files): array
    {
        return self::literalIds($files, self::ALTERATION);
    }

    /**
     * @param list<string> $files
     * @return array<non-empty-string, true>
     */
    private static function literalIds(array $files, string $pattern): array
    {
        $ids = [];
        foreach ($files as $file) {
            $source = is_file($file) ? file_get_contents($file) : false;
            $matches = [];
            if ($source === false || preg_match_all($pattern, $source, $matches) === 0) {
                continue;
            }

            foreach ($matches[2] as $match) {
                $id = Shape::nonEmptyString($match);
                if ($id !== null) {
                    $ids[$id] = true;
                }
            }
        }

        return $ids;
    }
}
