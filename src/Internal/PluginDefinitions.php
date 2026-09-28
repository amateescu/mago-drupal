<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Metadata\ClassLikeKind;
use Mago\Sdk\Analyzer\Metadata\ClassLikeMetadata;

use function array_intersect;
use function in_array;
use function strtolower;

/**
 * Which plugin classes get their definitions as arrays.
 *
 * Arrays are Drupal's default: annotation, attribute and YAML discovery all
 * produce them unless the plugin type opts into a definition class. In core
 * only layouts, Layout Builder section storage and CKEditor 5 plugins do, and
 * so does any plugin whose discovery attribute overrides `get()` to return
 * an object.
 *
 * @internal
 */
final class PluginDefinitions
{
    /**
     * Plugin interfaces whose definitions are objects, lowercased.
     */
    private const OBJECT_DEFINITION_TYPES = [
        'drupal\core\layout\layoutinterface',
        'drupal\layout_builder\sectionstorageinterface',
        'drupal\ckeditor5\plugin\ckeditor5plugininterface',
    ];

    /**
     * Plugin bases that plugin types of any kind extend, lowercased.
     */
    private const GENERIC_BASES = [
        'drupal\\component\\plugin\\pluginbase',
        'drupal\\core\\plugin\\pluginbase',
        'drupal\\core\\plugin\\configurablepluginbase',
    ];

    private const PLUGIN_ATTRIBUTE = 'drupal\component\plugin\attribute\attributeinterface';

    /**
     * Where the base plugin attribute declares `get()`, which returns an
     * array, lowercased.
     */
    private const ARRAY_ATTRIBUTE = 'drupal\component\plugin\attribute\attributebase';

    private function __construct() {}

    /**
     * Whether `$this` in the class, or in the classes using the trait, reads
     * an array definition.
     *
     * A class's own definition is an array unless its plugin type has
     * definition objects, whether or not an interface names the type. The
     * bases every plugin type extends say nothing, since a subclass of any
     * type may be `$this` there.
     */
    public static function ownAreArrays(Codebase $codebase, ClassLikeMetadata $class): bool
    {
        if ($class->kind !== ClassLikeKind::Trait) {
            return !in_array($class->name, self::GENERIC_BASES, strict: true) && self::areArrays($codebase, $class);
        }

        $users = TraitUsers::classes($codebase, $class->name);
        foreach ($users === [] ? [] : $codebase->getMultipleClassLikes($users) as $user) {
            if ($user === null || !self::ownAreArrays($codebase, $user)) {
                return false;
            }
        }

        return $users !== [];
    }

    public static function areArrays(Codebase $codebase, ClassLikeMetadata $class): bool
    {
        if (array_intersect(self::OBJECT_DEFINITION_TYPES, [$class->name, ...$class->parentInterfaces]) !== []) {
            return false;
        }

        foreach (self::pluginAttributes($codebase, $class) as $attribute) {
            $get = $codebase->getDeclaringMethod($attribute->name, 'get');
            if ($get !== null && strtolower($get->identifier->class ?? '') !== self::ARRAY_ATTRIBUTE) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the class declares its plugin type with an attribute.
     */
    public static function hasPluginAttribute(Codebase $codebase, ClassLikeMetadata $class): bool
    {
        return self::pluginAttributes($codebase, $class) !== [];
    }

    /**
     * The metadata of the class's plugin attributes.
     *
     * @return list<ClassLikeMetadata>
     */
    private static function pluginAttributes(Codebase $codebase, ClassLikeMetadata $class): array
    {
        $attributes = [];
        foreach ($class->attributes as $attribute) {
            $metadata = $codebase->getClassLike($attribute->name);
            if ($metadata !== null && in_array(self::PLUGIN_ATTRIBUTE, $metadata->parentInterfaces, strict: true)) {
                $attributes[] = $metadata;
            }
        }

        return $attributes;
    }
}
