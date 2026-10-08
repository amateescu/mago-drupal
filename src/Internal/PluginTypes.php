<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Metadata\ClassLikeKind;
use Mago\Sdk\Analyzer\Metadata\ClassLikeMetadata;

use function in_array;

/**
 * Whether a type names a plugin type, and so says whether a plugin of it has
 * an array definition.
 *
 * The receiver of `getPluginDefinition()` on another plugin is a class or an
 * interface. It names the plugin type through a plugin interface other than
 * the ones every type shares, or through a discovery attribute; then
 * `PluginDefinitions` says whether the type's definitions are arrays.
 *
 * @internal
 */
final class PluginTypes
{
    private const INSPECTION = 'drupal\component\plugin\plugininspectioninterface';

    /**
     * Plugin interfaces that plugin types with definition objects share with
     * the rest, lowercased.
     */
    private const GENERIC_INTERFACES = [
        self::INSPECTION,
        'drupal\component\plugin\contextawareplugininterface',
        'drupal\core\plugin\contextawareplugininterface',
    ];

    private function __construct() {}

    /**
     * Whether a plugin of this class or interface has an array definition.
     *
     * The type has to name the plugin type: through a plugin interface other
     * than the generic ones, or through a plugin attribute.
     */
    public static function haveArrays(Codebase $codebase, string $name): bool
    {
        $class = $codebase->getClassLike($name);
        if ($class === null || !self::isPlugin($class) || !PluginDefinitions::areArrays($codebase, $class)) {
            return false;
        }

        if (PluginDefinitions::hasPluginAttribute($codebase, $class)) {
            return true;
        }

        $interfaces = $class->kind === ClassLikeKind::Interface
            ? [$class->name, ...$class->parentInterfaces]
            : $class->parentInterfaces;
        foreach ($interfaces as $interface) {
            if (in_array($interface, self::GENERIC_INTERFACES, strict: true)) {
                continue;
            }

            $metadata = $codebase->getClassLike($interface);
            if ($metadata !== null && self::isPlugin($metadata)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the class or interface is a plugin.
     */
    private static function isPlugin(ClassLikeMetadata $class): bool
    {
        return $class->name === self::INSPECTION || in_array(self::INSPECTION, $class->parentInterfaces, strict: true);
    }
}
