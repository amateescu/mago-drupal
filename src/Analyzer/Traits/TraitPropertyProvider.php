<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Traits;

use amateescu\MagoDrupal\Analyzer\Entity\MagicProperties;
use amateescu\MagoDrupal\Internal\Cache\AnalysisMemo;
use amateescu\MagoDrupal\Internal\Traits\TraitRoots;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Metadata\ClassLikeKind;
use Mago\Sdk\Analyzer\Metadata\MemberIdentifier;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\PropertyTarget;
use Mago\Sdk\Analyzer\PropertyType;
use Mago\Sdk\Analyzer\PropertyTypeProvider;
use Mago\Sdk\Analyzer\PropertyTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

use function array_map;
use function strtolower;

/**
 * Types a property read on `$this` in a trait that every class using the
 * trait declares with the same type.
 *
 * Mago analyzes a trait once, on its own, so `$this->container` in a trait
 * that doesn't declare it is `mixed`, and so is everything called on it. The
 * provider answers with the type that the root classes in TraitRoots declare,
 * when every one of them declares the property with that type. Classes that
 * disagree leave the read `mixed`: the union of their types would report a
 * call once for each class whose type lacks the method. A write isn't
 * checked.
 *
 * Mago takes the answer as a magic property and reports that the trait
 * doesn't have `__get()`. TraitPropertyFilter drops that report.
 *
 * A target on a trait also matches the classes that use it, so the provider
 * is asked about their own properties of that name, and answers with nothing.
 *
 * @internal
 */
final class TraitPropertyProvider implements PropertyTypeProvider
{
    /**
     * The type for each trait and property, by lowercased class name.
     *
     * @var AnalysisMemo<Type|null>
     */
    private readonly AnalysisMemo $types;

    /**
     * @param non-empty-array<non-empty-string, non-empty-list<non-empty-string>> $names
     *   The names of the properties that each trait reads without declaring
     *   them, by the name of the trait.
     */
    public function __construct(
        private readonly TraitRoots $roots,
        private readonly array $names,
    ) {
        $this->types = new AnalysisMemo();
    }

    public function getTargets(): array
    {
        $targets = [];
        foreach ($this->names as $trait => $names) {
            foreach ($names as $name) {
                $targets[] = PropertyTarget::exact($trait, $name);
            }
        }

        return $targets;
    }

    public function getPropertyType(PropertyTypeProviderContext $context): ?PropertyType
    {
        $access = $context->access;
        $codebase = $context->codebase;
        $type = $this->types->get(
            $codebase,
            strtolower($access->class) . "\0" . $access->property,
            fn(): ?Type => $this->type($codebase, $access->class, $access->property),
        );

        return $type === null ? null : new PropertyType($type, Type::mixed());
    }

    /**
     * The type that every class using the trait declares for the property,
     * or null when the class isn't a trait, the trait has the property
     * itself, or the classes don't agree on one type.
     */
    private function type(Codebase $codebase, string $class, string $property): ?Type
    {
        if (
            $codebase->getClassLike($class)?->kind !== ClassLikeKind::Trait
            || MagicProperties::declaredOn($codebase, $class, $property)
        ) {
            return null;
        }

        $members = array_map(
            static fn(string $root): MemberIdentifier => new MemberIdentifier($root, '$' . $property),
            $this->roots->of($codebase, $class),
        );
        $agreed = null;
        foreach ($codebase->getMultipleDeclaringProperties($members) as $declared) {
            $type = $declared?->type?->type;
            if (
                $declared === null
                || $type === null
                || $declared->flags->contains(MetadataFlags::STATIC)
                || $agreed !== null && $agreed->encode() !== $type->encode()
            ) {
                return null;
            }

            $agreed = $type;
        }

        return $agreed === null || $agreed->encode() === Type::mixed()->encode() ? null : $agreed;
    }
}
