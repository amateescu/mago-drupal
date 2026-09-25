<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Metadata\AttributeMetadata;

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
     * reported as `MediaSource`.
     *
     * @param list<AttributeMetadata> $attributes
     * @param callable(string): (non-empty-string|null) $discoveredAttribute
     *   Maps an attribute class name to the discovered attribute class it is
     *   or extends, or null for any other attribute.
     * @return list<array{non-empty-string, non-empty-string}>
     */
    public static function read(array $attributes, callable $discoveredAttribute): array
    {
        $plugins = [];
        foreach ($attributes as $attribute) {
            $discovered = $discoveredAttribute($attribute->name);
            $id = Shape::nonEmptyString($attribute->getArgument(0, 'id')?->valueType?->getLiteralString());
            if ($discovered !== null && $id !== null) {
                $plugins[] = [$discovered, $id];
            }
        }

        return $plugins;
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
