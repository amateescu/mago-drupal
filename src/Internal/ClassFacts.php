<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Metadata\ClassLikeMetadata;
use Mago\Sdk\Analyzer\Metadata\MemberIdentifier;
use Mago\Sdk\Analyzer\Metadata\MethodFields;
use Mago\Sdk\Analyzer\Metadata\MethodMetadataProjection;
use Mago\Sdk\Analyzer\Metadata\PropertyMetadata;

use function array_key_exists;
use function in_array;
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
            if ($property === null || !$this->declaresProperty($property)) {
                continue;
            }

            $properties[] = $property;
        }

        return $this->properties = $properties;
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
     * Whether the property is declared in this class rather than by a trait
     * or a parent: its name sits in this class's file, inside the class span.
     */
    private function declaresProperty(PropertyMetadata $property): bool
    {
        $location = $property->nameLocation ?? $property->location;

        return (
            $location !== null
            && $location->file === $this->class->location->file
            && $this->class->location->span->contains($location->span)
        );
    }
}
