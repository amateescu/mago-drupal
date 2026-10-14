<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\PropertyAccess;

/**
 * Shared helper for the magic property providers.
 *
 * @internal
 */
final class MagicProperties
{
    private function __construct() {}

    /**
     * Whether PHP itself can resolve the accessed property.
     *
     * The host asks a provider about every property of a targeted class,
     * declared ones and `@property` tags included, and prefers the provider's
     * answer over the real type. Only names nothing else resolves are magic.
     */
    public static function declared(Codebase $codebase, PropertyAccess $access): bool
    {
        return self::declaredOn($codebase, $access->class, $access->property);
    }

    /**
     * Whether PHP itself can resolve the property on the class.
     *
     * @param string $property The name without the leading `$`.
     */
    public static function declaredOn(Codebase $codebase, string $class, string $property): bool
    {
        $name = '$' . $property;

        return $codebase->propertyExists($class, $name) || $codebase->magicPropertyExists($class, $name);
    }
}
