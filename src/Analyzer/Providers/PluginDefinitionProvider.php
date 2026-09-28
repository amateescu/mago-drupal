<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use amateescu\MagoDrupal\Internal\PluginDefinitions;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Metadata\ClassLikeKind;
use Mago\Sdk\Analyzer\Metadata\ClassLikeMetadata;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;

use function in_array;

/**
 * Types `getPluginDefinition()` as an array for plugin types whose
 * definitions are arrays.
 *
 * `PluginInspectionInterface` documents the definition as
 * `array|PluginDefinitionInterface`, so Mago reports every `['key']` read on
 * it, directly or through a variable. The receiver says the plugin type:
 * `PluginDefinitions` knows the types with definition objects, and the rest
 * use arrays. `PluginBase`, the context-aware interfaces and traits say
 * nothing about the type, so a call on them keeps core's union.
 *
 * @internal
 */
final class PluginDefinitionProvider implements MethodReturnTypeProvider
{
    private const INSPECTION = 'drupal\component\plugin\plugininspectioninterface';

    /**
     * Plugin interfaces that plugin types with definition objects share with
     * the rest, lowercased.
     */
    private const GENERIC = [
        self::INSPECTION,
        'drupal\component\plugin\contextawareplugininterface',
        'drupal\core\plugin\contextawareplugininterface',
    ];

    public function getTargets(): array
    {
        return [MethodTarget::exact('Drupal\Component\Plugin\PluginInspectionInterface', 'getPluginDefinition')];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $receiver = $context->invocation->receiverType;
        if ($receiver === null) {
            return null;
        }

        foreach ($receiver->atomicTypes as $atomic) {
            if (!$atomic instanceof NamedObjectType || !self::hasArrays($context->codebase, $atomic->name)) {
                return null;
            }
        }

        return Type::array(Type::string(), Type::mixed());
    }

    /**
     * Whether a plugin of this class or interface has an array definition.
     *
     * The type has to name the plugin type: through a plugin interface other
     * than the generic ones, or through a plugin attribute.
     */
    public static function hasArrays(Codebase $codebase, string $name): bool
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
            if (in_array($interface, self::GENERIC, strict: true)) {
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
