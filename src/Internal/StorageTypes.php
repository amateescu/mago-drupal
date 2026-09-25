<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Codebase;

use function in_array;
use function preg_match;
use function strtolower;

/**
 * Tells entity storage types apart from other types named like them.
 *
 * @internal
 */
final class StorageTypes
{
    private const SUFFIXES = ['Storage', 'StorageInterface'];

    private const STORAGE = 'Drupal\Core\Entity\EntityStorageInterface';

    /**
     * A `@var` tag whose type has a name ending in one of the SUFFIXES.
     */
    private const VAR_TAG = '/@var\s+[^\s*]*Storage(?:Interface)?(?![\w\\\\])/';

    private function __construct() {}

    /**
     * Whether a name looks like a storage: it ends in `Storage` or
     * `StorageInterface`. A cheap gate on the names a class mentions, before
     * any() looks the class up.
     *
     * @param list<non-empty-string> $types
     */
    public static function namedLike(array $types): bool
    {
        return ClassNames::anyEndsWith($types, self::SUFFIXES);
    }

    /**
     * Whether a class's text has a `@var` tag naming a storage. A type that
     * only a docblock names is not among the names the parser resolves, so
     * namedLike() misses a property typed that way.
     */
    public static function documentedLike(string $text): bool
    {
        return preg_match(self::VAR_TAG, $text) === 1;
    }

    /**
     * Whether one of the types is an entity storage: named like one, and
     * `EntityStorageInterface` or a class or interface that extends it. The
     * ancestry decides, since core has storages outside any `Entity`
     * namespace, such as `RoleStorageInterface`, and config and key-value
     * storages share the name ending.
     *
     * @param list<non-empty-string> $types
     */
    public static function any(Codebase $codebase, array $types): bool
    {
        $storage = strtolower(self::STORAGE);
        foreach ($types as $type) {
            if (!self::namedLike([$type])) {
                continue;
            }

            if (strtolower($type) === $storage) {
                return true;
            }

            $class = $codebase->getClassLike($type);
            if ($class !== null && in_array($storage, $class->parentInterfaces, strict: true)) {
                return true;
            }
        }

        return false;
    }
}
