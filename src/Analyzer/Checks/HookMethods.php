<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Checks;

use amateescu\MagoDrupal\Internal\Attributes;
use amateescu\MagoDrupal\Internal\ClassFacts;
use amateescu\MagoDrupal\Internal\ClassNames;
use amateescu\MagoDrupal\Internal\Types;
use Mago\Sdk\Analyzer\Metadata\MethodMetadataProjection;
use Mago\Sdk\Analyzer\Metadata\ParameterMetadata;

/**
 * Shared reading of `#[Hook('name')]` methods for the hook checks.
 *
 * @internal
 */
final class HookMethods
{
    public const ATTRIBUTE = 'Drupal\Core\Hook\Attribute\Hook';

    private function __construct() {}

    /**
     * Every hook a class implements, as `[hook name, method]` pairs. A
     * class-level attribute names its method, `__invoke()` by default.
     *
     * @return list<array{non-empty-string, MethodMetadataProjection}>
     */
    public static function of(ClassFacts $class): array
    {
        $hooks = [];
        foreach (Attributes::named($class->class->attributes, self::ATTRIBUTE) as $attribute) {
            $hook = Attributes::string($attribute, 0, 'hook');
            $method = $class->method(Attributes::string($attribute, 1, 'method') ?? '__invoke');
            if ($hook !== null && $hook !== '' && $method !== null) {
                $hooks[] = [$hook, $method];
            }
        }

        foreach ($class->methodsWithAttribute(self::ATTRIBUTE) as $method) {
            foreach (Attributes::named($method->attributes ?? [], self::ATTRIBUTE) as $attribute) {
                $hook = Attributes::string($attribute, 0, 'hook');
                if ($hook !== null && $hook !== '') {
                    $hooks[] = [$hook, $method];
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
