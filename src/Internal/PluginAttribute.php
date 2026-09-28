<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Metadata\AttributeMetadata;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\ReferenceSelectorKind;
use Mago\Sdk\Analyzer\Type\ReferenceType;
use Mago\Sdk\Analyzer\Type\ReferenceTypeKind;

use function count;
use function strcasecmp;

/**
 * Reads the plugin declarations off one class's attributes.
 *
 * @internal
 */
final class PluginAttribute
{
    private function __construct() {}

    /**
     * Returns `[attribute class, plugin id]` pairs. The attribute class is the
     * one a manager discovers, so a subclass such as `OEmbedMediaSource` is
     * reported as `MediaSource`. The id is a literal string or a class
     * constant holding one, as in `id: LanguageNegotiationUrl::METHOD_ID`.
     *
     * @param list<AttributeMetadata> $attributes
     * @param callable(string): (non-empty-string|null) $discoveredAttribute
     *   Maps an attribute class name to the discovered attribute class it is
     *   or extends, or null for any other attribute.
     * @param callable(string, string): (non-empty-string|null) $constantValue
     *   Maps a class and a constant name to the literal string the constant
     *   holds, or null.
     * @return list<array{non-empty-string, non-empty-string}>
     */
    public static function read(array $attributes, callable $discoveredAttribute, callable $constantValue): array
    {
        $plugins = [];
        foreach ($attributes as $attribute) {
            $discovered = $discoveredAttribute($attribute->name);
            if ($discovered === null) {
                continue;
            }

            $value = $attribute->getArgument(0, 'id')?->valueType;
            $id = Shape::nonEmptyString($value?->getLiteralString()) ?? self::constant($value, $constantValue);
            if ($id !== null) {
                $plugins[] = [$discovered, $id];
            }
        }

        return $plugins;
    }

    /**
     * The value of the class constant an argument names, which Mago keeps
     * as a reference to the constant.
     *
     * @param callable(string, string): (non-empty-string|null) $constantValue
     * @return non-empty-string|null
     */
    private static function constant(?Type $value, callable $constantValue): ?string
    {
        $atomic = $value !== null && count($value->atomicTypes) === 1 ? $value->atomicTypes[0] : null;
        if (
            !$atomic instanceof ReferenceType
            || $atomic->kind !== ReferenceTypeKind::Member
            || $atomic->selector !== ReferenceSelectorKind::Identifier
            || $atomic->name === null
            || $atomic->member === null
        ) {
            return null;
        }

        return $constantValue($atomic->name, $atomic->member);
    }

    /**
     * Resolves an attribute class to the discovered attribute it is or extends.
     *
     * @param list<string> $ancestors The attribute class's ancestors.
     * @return non-empty-string|null
     */
    public static function discovered(string $attribute, array $ancestors): ?string
    {
        foreach ([$attribute, ...$ancestors] as $candidate) {
            foreach (PluginManagers::ATTRIBUTES as $known) {
                if (strcasecmp($candidate, $known) === 0) {
                    return $known;
                }
            }
        }

        return null;
    }
}
