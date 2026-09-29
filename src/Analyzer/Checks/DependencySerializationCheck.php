<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Checks;

use amateescu\MagoDrupal\Internal\ClassFacts;
use amateescu\MagoDrupal\Internal\ParentProperties;
use amateescu\MagoDrupal\Internal\TraitComposers;
use amateescu\MagoDrupal\Internal\Types;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\Type\Visibility;

use function array_key_exists;
use function array_map;
use function in_array;
use function strtolower;

/**
 * Reports properties DependencySerializationTrait cannot restore.
 *
 * Ports phpstan-drupal's DependencySerializationTraitPropertyRule. The trait's
 * `__wakeup()` writes properties by name. A private property declared in a
 * class other than the one composing the trait is invisible to it. A readonly
 * property declared below the composing class cannot be written from the
 * trait's scope before PHP 8.4. A private property is reported even in the
 * composing class, since a subclass's `__sleep()` would have to name it
 * mangled. The trait's `__sleep()` lists what `get_object_vars()` sees in the
 * composing class's scope, so a private property of a parent class is never
 * serialized; the class composing the trait reports those.
 *
 * A class composes the trait when its body uses the trait, or a trait that
 * uses it, since trait methods run in the scope of the class that uses them.
 * The class hook checks the classes that compose it, and a descendant hook
 * on the classes `TraitComposers` finds checks their descendants.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class DependencySerializationCheck implements MetadataCheck
{
    public const CODE = 'dependency-serialization-property';

    public const TRAIT = 'Drupal\Core\DependencyInjection\DependencySerializationTrait';

    private const LINK = 'https://www.drupal.org/node/3110266';

    /**
     * Core bases that use the trait, for a root whose core is not on disk
     * next to it. The scan finds these and the others when it is.
     */
    private const CORE_BASES = [
        'Drupal\Core\Form\FormBase',
        'Drupal\Core\Plugin\PluginBase',
        'Drupal\Core\Entity\EntityHandlerBase',
    ];

    public function __construct(
        private readonly TraitComposers $composers,
    ) {}

    /**
     * The classes under the root that use the trait, whose descendants
     * inherit it. A class using it itself is caught by the mention of the
     * trait in its body.
     *
     * @return list<non-empty-string>
     */
    public function bases(): array
    {
        $bases = [];
        foreach ([...self::CORE_BASES, ...$this->composers->classes] as $class) {
            $bases[strtolower($class)] = $class;
        }

        return array_values($bases);
    }

    /**
     * Whether the class body names the trait or a trait that uses it.
     *
     * @param array<string, true> $mentions Lowercased resolved names.
     */
    public function namedBy(array $mentions): bool
    {
        foreach ($this->traits() as $trait) {
            if (array_key_exists(strtolower($trait), $mentions)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The trait and the traits under the root that use it.
     *
     * @return non-empty-list<non-empty-string>
     */
    private function traits(): array
    {
        return [self::TRAIT, ...$this->composers->traits];
    }

    /**
     * Only a private or readonly property can be reported.
     */
    public function textGate(): ?string
    {
        return '/\b(?:private|readonly)\b/i';
    }

    public function check(ClassFacts $class, Reporter $reporter): void
    {
        // The metadata trait list includes the parents' and nested traits.
        if (!$class->composes(self::TRAIT)) {
            return;
        }

        $composesTrait = false;
        foreach ($this->traits() as $trait) {
            $composesTrait = $composesTrait || $class->mentions($trait);
        }

        // Below a base that composes the trait, the base's composing class
        // reports the parents' properties.
        if ($composesTrait && !$class->extendsAny($this->bases())) {
            $this->checkParents($class, $reporter);
        }

        foreach ($class->properties() as $property) {
            $location = $property->nameLocation ?? $property->location;
            if ($location === null || $property->flags->contains(MetadataFlags::STATIC)) {
                continue;
            }

            if ($property->readVisibility === Visibility::Private) {
                $reporter->error(self::CODE, Reporter::issue(
                    "DependencySerializationTrait does not support the private property {$property->name}.",
                    $location,
                    'Make it protected, or serialize the class another way.',
                    self::LINK,
                ));
                continue;
            }

            if (
                !$property->flags->contains(MetadataFlags::READONLY)
                || $composesTrait
                || Types::isScalarOnly($property->declaredType?->type)
            ) {
                continue;
            }

            if (!self::beforePhp84($reporter)) {
                continue;
            }

            $reporter->error(self::CODE, Reporter::issue(
                "The readonly property {$property->name} cannot be restored by a parent's DependencySerializationTrait before PHP 8.4.",
                $location,
                'Use the trait in this class directly, or drop readonly.',
                self::LINK,
            ));
        }
    }

    /**
     * Reports the properties of the parents of a class whose own body
     * composes the trait that the trait cannot restore, at the class. See
     * ParentProperties.
     */
    private function checkParents(ClassFacts $class, Reporter $reporter): void
    {
        $lost = ParentProperties::lost($class->codebase, $class->class->parentClasses, strtolower(self::TRAIT));
        [$private, $readonly] = $lost ?? [[], []];
        // PHP 8.4 lets a parent scope initialize a child's readonly property.
        $readonly = self::beforePhp84($reporter) ? $readonly : [];
        if ($private === [] && $readonly === [] || $this->serializesItself($class)) {
            return;
        }

        $name = $class->name();
        $where = $class->class->nameLocation ?? $class->class->location;
        foreach ($private as $property) {
            $reporter->error(self::CODE, Reporter::issue(
                "DependencySerializationTrait in {$name} does not serialize the private property {$property} of a parent class.",
                $where,
                'Make it protected, or serialize the class another way.',
                self::LINK,
            ));
        }

        foreach ($readonly as $property) {
            $reporter->error(self::CODE, Reporter::issue(
                "The readonly property {$property} of a parent class cannot be restored by DependencySerializationTrait in {$name} before PHP 8.4.",
                $where,
                'Use the trait in that parent class, or drop readonly.',
                self::LINK,
            ));
        }
    }

    /**
     * Whether the analyzed PHP version is older than 8.4, which lets a
     * parent scope initialize a child's readonly property.
     */
    private static function beforePhp84(Reporter $reporter): bool
    {
        $version = $reporter->phpVersion;

        return $version->major() < 8 || $version->major() === 8 && $version->minor() < 4;
    }

    /**
     * Whether the class serializes itself another way: a `__sleep()` of its
     * own replaces the trait's, and a `__serialize()` anywhere takes over
     * from `__sleep()`.
     */
    private function serializesItself(ClassFacts $class): bool
    {
        $traits = array_map(strtolower(...), $this->traits());
        foreach ($class->codebase->findMethods(class: $class->class->name, name: '__s*', fields: 0) as $method) {
            $member = strtolower($method->method->member);
            if (
                $member === '__serialize'
                || $member === '__sleep'
                && !in_array(strtolower($method->identifier->class ?? ''), $traits, strict: true)
            ) {
                return true;
            }
        }

        return false;
    }
}
