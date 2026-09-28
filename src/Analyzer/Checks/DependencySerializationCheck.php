<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Checks;

use amateescu\MagoDrupal\Internal\ClassFacts;
use amateescu\MagoDrupal\Internal\TraitComposers;
use amateescu\MagoDrupal\Internal\Types;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\Type\Visibility;

use function array_key_exists;
use function array_values;
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
 * mangled.
 *
 * A class composes the trait when its body uses the trait, or a trait that
 * uses it, since trait methods run in the scope of the class that uses them.
 * The class hook checks the classes that compose it, and a descendant hook
 * on the classes `TraitComposers` finds checks their descendants.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
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

            // PHP 8.4 lets a parent scope initialize a child's readonly property.
            if (
                $reporter->phpVersion->major() > 8
                || $reporter->phpVersion->major() === 8 && $reporter->phpVersion->minor() >= 4
            ) {
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
}
