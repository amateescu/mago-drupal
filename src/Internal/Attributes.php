<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Metadata\AttributeMetadata;

use function strtolower;

/**
 * Reads attribute metadata the way the checks need it.
 *
 * @internal
 */
final class Attributes
{
    private function __construct() {}

    /**
     * The attributes of the given class among a list, compared by name
     * regardless of case.
     *
     * @param list<AttributeMetadata> $attributes
     * @return list<AttributeMetadata>
     */
    public static function named(array $attributes, string $class): array
    {
        $found = [];
        foreach ($attributes as $attribute) {
            if (strtolower($attribute->name) !== strtolower($class)) {
                continue;
            }

            $found[] = $attribute;
        }

        return $found;
    }

    /**
     * The literal string an argument holds, by position or name.
     */
    public static function string(AttributeMetadata $attribute, int $position, string $name): ?string
    {
        return $attribute->getArgument($position, $name)?->valueType?->getLiteralString();
    }
}
