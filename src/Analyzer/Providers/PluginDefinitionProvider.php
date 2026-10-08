<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use amateescu\MagoDrupal\Internal\PluginDefinitions;
use amateescu\MagoDrupal\Internal\PluginTypes;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;

/**
 * Types `getPluginDefinition()` as an array for plugin types whose
 * definitions are arrays.
 *
 * `PluginInspectionInterface` documents the definition as
 * `array|PluginDefinitionInterface`, so Mago reports every `['key']` read on
 * it, directly or through a variable. The receiver says the plugin type:
 * `PluginDefinitions` knows the types with definition objects, and the rest
 * use arrays. `PluginBase`, the context-aware interfaces and traits say
 * nothing about the type, so a call on them keeps core's union. On `$this`
 * the call reads the plugin's own definition, which is an array unless the
 * class is a generic base or its type has definition objects.
 *
 * @internal
 */
final class PluginDefinitionProvider implements MethodReturnTypeProvider
{
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
            if (!$atomic instanceof NamedObjectType || !self::receiverHasArrays($context->codebase, $atomic)) {
                return null;
            }
        }

        return Type::array(Type::string(), Type::mixed());
    }

    /**
     * Whether the receiver's definition is an array: the plugin's own, for
     * `$this`, or one whose type the receiver names.
     */
    private static function receiverHasArrays(Codebase $codebase, NamedObjectType $receiver): bool
    {
        if (!$receiver->isThis) {
            return PluginTypes::haveArrays($codebase, $receiver->name);
        }

        // The target only matches plugin classes, so `$this` is one.
        $class = $codebase->getClassLike($receiver->name);

        return $class !== null && PluginDefinitions::ownAreArrays($codebase, $class);
    }
}
