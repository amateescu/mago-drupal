<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Metadata\ClassLikeMetadata;
use Mago\Sdk\Analyzer\Metadata\MemberIdentifier;
use Mago\Sdk\Analyzer\Metadata\MethodFields;
use Mago\Sdk\Analyzer\Metadata\MethodMetadataProjection;
use Mago\Sdk\Analyzer\Metadata\PropertyMetadata;

use function array_filter;
use function array_key_exists;
use function array_values;
use function in_array;
use function str_starts_with;
use function strtolower;

/**
 * One class as the checks see it: its metadata plus its own methods and
 * properties, fetched from the codebase on first use.
 *
 * The class metadata lists member names only. Methods come through one
 * `findMethods()` call and properties through one `getMultipleProperties()`
 * call, both limited to the class's own declarations.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class ClassFacts
{
    public const METHOD_FIELDS =
        MethodFields::NAMES
            | MethodFields::LOCATIONS
            | MethodFields::PARAMETERS
            | MethodFields::ATTRIBUTES
            | MethodFields::METHOD_DETAILS
            | MethodFields::FLAGS;

    /**
     * @var list<MethodMetadataProjection>|null
     */
    private ?array $methods = null;

    /**
     * @var array<string, list<MethodMetadataProjection>>
     */
    private array $attributed = [];

    /**
     * @var list<PropertyMetadata>|null
     */
    private ?array $properties = null;

    /**
     * @param array<string, true> $mentions Lowercased names the class body
     *   mentions, from the node's resolved names; empty when unknown.
     */
    public function __construct(
        public readonly ClassLikeMetadata $class,
        public readonly Codebase $codebase,
        private readonly array $mentions = [],
    ) {}

    /**
     * Whether the class body names the class itself, as a parent, trait,
     * attribute or type; inherited members do not count.
     */
    public function mentions(string $class): bool
    {
        return array_key_exists(strtolower($class), $this->mentions);
    }

    /**
     * @return non-empty-string
     */
    public function name(): string
    {
        /** @var non-empty-string */
        return $this->class->originalName;
    }

    /**
     * The class's own methods.
     *
     * @return list<MethodMetadataProjection>
     */
    public function methods(): array
    {
        return $this->methods ??= $this->codebase->findMethods(
            class: $this->class->name,
            fields: self::METHOD_FIELDS,
            declaredOnly: true,
        );
    }

    public function method(string $name): ?MethodMetadataProjection
    {
        foreach ($this->methods() as $method) {
            if (strtolower($method->name ?? '') === strtolower($name)) {
                return $method;
            }
        }

        return null;
    }

    /**
     * The method of that name the class has, its own or inherited.
     */
    public function visibleMethod(string $name): ?MethodMetadataProjection
    {
        return (
            $this->method($name)
            ?? $this->codebase->findMethods(class: $this->class->name, name: $name, fields: self::METHOD_FIELDS)[0]
            ?? null
        );
    }

    public function constructor(): ?MethodMetadataProjection
    {
        return $this->method('__construct');
    }

    /**
     * The class's own methods carrying the attribute.
     *
     * @return list<MethodMetadataProjection>
     */
    public function methodsWithAttribute(string $attribute): array
    {
        return $this->attributed[strtolower($attribute)] ??= $this->codebase->findMethods(
            class: $this->class->name,
            withAnyAttribute: [$attribute],
            fields: self::METHOD_FIELDS,
            declaredOnly: true,
        );
    }

    /**
     * The class's own properties, promoted ones included.
     *
     * @return list<PropertyMetadata>
     */
    public function properties(): array
    {
        if ($this->properties !== null) {
            return $this->properties;
        }

        $identifiers = [];
        foreach ($this->class->properties as $name) {
            $identifiers[] = new MemberIdentifier($this->class->name, $name);
        }

        $properties = [];
        foreach ($identifiers === [] ? [] : $this->codebase->getMultipleProperties($identifiers) as $property) {
            if ($property === null || !self::declares($this->class, $property)) {
                continue;
            }

            $properties[] = $property;
        }

        return $this->properties = $properties;
    }

    /**
     * The properties that the traits in Drupal's namespace named in the class
     * body bring in, each with its trait. PHP copies them into the class, so
     * they are the class's own at runtime.
     *
     * @return list<array{ClassLikeMetadata, PropertyMetadata}>
     */
    public function traitProperties(): array
    {
        $named = array_values(array_filter(
            $this->class->usedTraits,
            fn(string $trait): bool => str_starts_with($trait, 'drupal\\') && $this->mentions($trait),
        ));
        $traits = [];
        foreach ($named === [] ? [] : $this->codebase->getMultipleClassLikes($named) as $trait) {
            if ($trait === null) {
                continue;
            }

            $traits[] = $trait;
        }

        $identifiers = [];
        foreach ($traits === [] ? [] : $this->class->properties as $name) {
            $identifiers[] = new MemberIdentifier($this->class->name, $name);
        }

        $found = [];
        foreach ($identifiers === [] ? [] : $this->codebase->getMultipleProperties($identifiers) as $property) {
            if ($property === null) {
                continue;
            }

            foreach ($traits as $trait) {
                if (!self::declares($trait, $property)) {
                    continue;
                }

                $found[] = [$trait, $property];
                break;
            }
        }

        return $found;
    }

    public function property(string $name): ?PropertyMetadata
    {
        foreach ($this->properties() as $property) {
            if ($property->name === $name) {
                return $property;
            }
        }

        return null;
    }

    public function composes(string $trait): bool
    {
        return in_array(strtolower($trait), $this->class->usedTraits, strict: true);
    }

    /**
     * @param list<string> $classes
     */
    public function extendsAny(array $classes): bool
    {
        foreach ($classes as $class) {
            if (in_array(strtolower($class), $this->class->parentClasses, strict: true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $interfaces
     */
    public function implementsAny(array $interfaces): bool
    {
        foreach ($interfaces as $interface) {
            if (in_array(strtolower($interface), $this->class->parentInterfaces, strict: true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the property is declared in the class's own body rather than
     * by a trait or a parent: its name sits in the class's file, inside the
     * class span.
     */
    public static function declares(ClassLikeMetadata $class, PropertyMetadata $property): bool
    {
        $location = $property->nameLocation ?? $property->location;

        return (
            $location !== null
            && $location->file === $class->location->file
            && $class->location->span->contains($location->span)
        );
    }
}
