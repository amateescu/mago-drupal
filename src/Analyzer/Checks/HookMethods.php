<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Checks;

use amateescu\MagoDrupal\Internal\Attributes;
use amateescu\MagoDrupal\Internal\ClassFacts;
use amateescu\MagoDrupal\Internal\ClassNames;
use amateescu\MagoDrupal\Internal\Types;
use Mago\Sdk\Analyzer\Metadata\MethodMetadataProjection;
use Mago\Sdk\Analyzer\Metadata\ParameterMetadata;
use Mago\Sdk\SourceLocation;

/**
 * Shared reading of `#[Hook('name')]` methods for the hook checks.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class HookMethods
{
    public const ATTRIBUTE = 'Drupal\Core\Hook\Attribute\Hook';

    private function __construct() {}

    /**
     * Every hook a class implements, as `[hook name, method, location]`
     * triples. A class-level attribute names its method, `__invoke()` by
     * default.
     *
     * The location is where a report about the implementation goes: the
     * method's name, or the class-level attribute when the method is
     * inherited. A parent's method is then reported once for each class that
     * names it, in that class's file, and never in a file the run only
     * includes.
     *
     * @return list<array{non-empty-string, MethodMetadataProjection, SourceLocation}>
     */
    public static function of(ClassFacts $class): array
    {
        $hooks = [];
        foreach (Attributes::named($class->class->attributes, self::ATTRIBUTE) as $attribute) {
            $hook = Attributes::string($attribute, 0, 'hook');
            $name = Attributes::string($attribute, 1, 'method') ?? '__invoke';
            $own = $class->method($name);
            $method = $own ?? $class->visibleMethod($name);
            $location = $own === null ? $attribute->location : $own->nameLocation ?? $own->location;
            if ($hook !== null && $hook !== '' && $method !== null && $location !== null) {
                $hooks[] = [$hook, $method, $location];
            }
        }

        foreach ($class->methodsWithAttribute(self::ATTRIBUTE) as $method) {
            $location = $method->nameLocation ?? $method->location;
            foreach (Attributes::named($method->attributes ?? [], self::ATTRIBUTE) as $attribute) {
                $hook = Attributes::string($attribute, 0, 'hook');
                if ($hook !== null && $hook !== '' && $location !== null) {
                    $hooks[] = [$hook, $method, $location];
                }
            }
        }

        return $hooks;
    }

    /**
     * Whether a parameter is typed as the class, or as something named like a
     * subtype of it.
     *
     * @param list<string> $classes
     */
    public static function typed(?ParameterMetadata $parameter, array $classes): bool
    {
        return $parameter !== null && ClassNames::anyEndsWith(Types::names($parameter->declaredType?->type), $classes);
    }

    /**
     * @return list<ParameterMetadata>
     */
    public static function parameters(MethodMetadataProjection $method): array
    {
        return $method->parameters ?? [];
    }

    public static function label(MethodMetadataProjection $method): string
    {
        return $method->originalName ?? $method->name ?? $method->method->member;
    }
}
