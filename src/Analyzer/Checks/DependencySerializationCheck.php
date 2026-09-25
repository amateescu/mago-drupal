<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Checks;

use amateescu\MagoDrupal\Internal\ClassFacts;
use amateescu\MagoDrupal\Internal\Types;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\Type\Visibility;

/**
 * Reports properties DependencySerializationTrait cannot restore.
 *
 * Ports phpstan-drupal's DependencySerializationTraitPropertyRule: the trait's
 * `__wakeup()` writes properties by name, so a private property declared in a
 * class other than the one composing the trait is invisible to it, and a
 * readonly property declared in a child class cannot be written from the
 * trait's scope before PHP 8.4.
 *
 * @internal
 */
final class DependencySerializationCheck implements MetadataCheck
{
    public const CODE = 'dependency-serialization-property';

    public const TRAIT = 'Drupal\Core\DependencyInjection\DependencySerializationTrait';

    /**
     * Core bases that compose the trait, so their descendants inherit it. The
     * hook is registered for these; a class composing the trait itself is
     * caught by the mention of the trait in its body. Controllers, plugin
     * forms and views plugins do not get the trait from their bases.
     */
    public const BASES = [
        'Drupal\Core\Form\FormBase',
        'Drupal\Core\Plugin\PluginBase',
        'Drupal\Core\Entity\EntityHandlerBase',
    ];

    private const LINK = 'https://www.drupal.org/node/3110266';

    public function mentionsAny(): array
    {
        return [];
    }

    public function check(ClassFacts $class, Reporter $reporter): void
    {
        // The metadata trait list includes the parents' traits. Checking it
        // here keeps the check right for a core version where one of the
        // bases stops using the trait.
        if (!$class->composes(self::TRAIT)) {
            return;
        }

        // The class composes the trait itself when its own body names it.
        $composesTrait = $class->mentions(self::TRAIT);
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
