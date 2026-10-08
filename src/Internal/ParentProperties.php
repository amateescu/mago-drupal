<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Metadata\ClassLikeMetadata;
use Mago\Sdk\Analyzer\Metadata\MemberIdentifier;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\Metadata\PropertyMetadata;
use Mago\Sdk\Analyzer\Type\Visibility;

use function array_key_exists;
use function array_values;
use function in_array;
use function str_starts_with;
use function strtolower;

/**
 * The properties of a class's parents that a trait used in the class cannot
 * restore on unserialize.
 *
 * A trait method runs in the scope of the class that uses it, so a private
 * property of a parent is out of its reach, and before PHP 8.4 so is writing
 * a readonly property of a parent. Only parents in Drupal's namespace count.
 * A Symfony or PHPUnit parent, such as a session handler base or `TestCase`,
 * is not the module's to change. A property that a trait in Drupal's
 * namespace brings into a parent belongs to the parent's scope, so it counts
 * too.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class ParentProperties
{
    private function __construct() {}

    /**
     * The private properties and the readonly non-scalar ones, as
     * `Parent::$name`. Null when a parent uses the trait itself, so a class
     * further up reports them.
     *
     * @param list<string> $parents Every parent class of the class.
     * @param string $trait The trait, lowercased.
     * @return array{list<string>, list<string>}|null
     */
    public static function lost(Codebase $codebase, array $parents, string $trait): ?array
    {
        $found = self::drupalParents($codebase, $parents, $trait);
        if ($found === null) {
            return null;
        }

        [$drupalParents, $traits] = $found;
        $identifiers = [];
        $declarers = [];
        foreach ($drupalParents as $parent) {
            $own = [$parent];
            foreach ($parent->usedTraits as $used) {
                if (!array_key_exists($used, $traits)) {
                    continue;
                }

                $own[] = $traits[$used];
            }

            foreach ($parent->properties as $property) {
                $identifiers[] = new MemberIdentifier($parent->originalName, $property);
                $declarers[] = $own;
            }
        }

        $private = [];
        $readonly = [];
        foreach ($identifiers === [] ? [] : $codebase->getMultipleProperties($identifiers) as $index => $property) {
            if (
                $property === null
                || $property->flags->contains(MetadataFlags::STATIC)
                || !self::declaredByAny($declarers[$index], $property)
            ) {
                continue;
            }

            $name = $identifiers[$index]->class . '::' . $property->name;
            if ($property->readVisibility === Visibility::Private) {
                $private[] = $name;
                continue;
            }

            if (
                $property->flags->contains(MetadataFlags::READONLY)
                && !Types::isScalarOnly($property->declaredType?->type)
            ) {
                $readonly[] = $name;
            }
        }

        return [$private, $readonly];
    }

    /**
     * The parents in Drupal's namespace, and the traits in Drupal's
     * namespace they use keyed by lowercased name. Null when a parent uses
     * the trait.
     *
     * @param list<string> $parents
     * @return array{list<ClassLikeMetadata>, array<string, ClassLikeMetadata>}|null
     */
    private static function drupalParents(Codebase $codebase, array $parents, string $trait): ?array
    {
        $drupalParents = [];
        $traitNames = [];
        foreach ($parents === [] ? [] : $codebase->getMultipleClassLikes($parents) as $parent) {
            if ($parent === null) {
                continue;
            }

            if (in_array($trait, $parent->usedTraits, strict: true)) {
                return null;
            }

            if (!self::inDrupal($parent->originalName)) {
                continue;
            }

            $drupalParents[] = $parent;
            foreach ($parent->usedTraits as $used) {
                $traitNames[$used] = $used;
            }
        }

        $traits = [];
        foreach ($traitNames === [] ? [] : $codebase->getMultipleClassLikes(array_values($traitNames)) as $used) {
            if ($used === null || !self::inDrupal($used->originalName)) {
                continue;
            }

            $traits[strtolower($used->name)] = $used;
        }

        return [$drupalParents, $traits];
    }

    /**
     * Whether one of the class-likes declares the property in its own body.
     *
     * @param list<ClassLikeMetadata> $declarers
     */
    private static function declaredByAny(array $declarers, PropertyMetadata $property): bool
    {
        foreach ($declarers as $declarer) {
            if (ClassFacts::declares($declarer, $property)) {
                return true;
            }
        }

        return false;
    }

    private static function inDrupal(string $name): bool
    {
        return str_starts_with(strtolower($name), 'drupal\\');
    }
}
