<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use amateescu\MagoDrupal\Internal\PluginIndex;
use amateescu\MagoDrupal\Internal\PluginManagers;
use amateescu\MagoDrupal\Internal\Types;
use Closure;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;

/**
 * Types `$manager->createInstance('id')` for core's attribute-based managers.
 *
 * The receiver's class says which attribute the manager discovers, the
 * plugin index says which class carries that attribute with the given id.
 *
 * @internal
 */
final class PluginManagerProvider implements MethodReturnTypeProvider
{
    public const FACTORY = 'Drupal\Component\Plugin\Factory\FactoryInterface';

    /**
     * @param Closure(Codebase): PluginIndex $index
     */
    public function __construct(
        private readonly Closure $index,
    ) {}

    public function getTargets(): array
    {
        return [MethodTarget::exact(self::FACTORY, 'createInstance')];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $invocation = $context->invocation;
        $attribute = self::attributeOf($context->codebase, $invocation->receiverType);
        $id = PluginIds::fromInvocation($invocation);
        if ($attribute === null || $id === null) {
            return null;
        }

        $index = ($this->index)($context->codebase);
        $class = $index->classOf($attribute, $id);
        // A fallback manager hands out its fallback plugin for an id nothing
        // declares, instead of throwing, so that is the type. An id two
        // classes declare is a real plugin whose class is unknown.
        $fallback = PluginManagers::FALLBACKS[$attribute] ?? null;
        if ($class === null && $fallback !== null && !$index->declares($attribute, $id)) {
            $class = $index->classOf($attribute, $fallback);
        }

        return $class === null || !$context->codebase->classLikeExists($class) ? null : Type::namedObject($class);
    }

    /**
     * The plugin attribute the receiver manager discovers, when every manager
     * the receiver may be discovers the same one.
     *
     * @return non-empty-string|null
     */
    public static function attributeOf(Codebase $codebase, ?Type $receiver): ?string
    {
        return Types::agreed($receiver, static fn(NamedObjectType $atomic): ?string => PluginManagers::attributeFor(
            $codebase,
            $atomic->name,
        ));
    }
}
