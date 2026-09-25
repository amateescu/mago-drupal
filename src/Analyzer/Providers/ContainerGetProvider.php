<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use amateescu\MagoDrupal\Internal\Arguments;
use amateescu\MagoDrupal\Internal\ServiceIndex;
use Closure;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

/**
 * Types container lookups from the service index.
 *
 * `$container->get('entity_type.manager')` is declared `?object`. When the id
 * is a literal that the index knows, the call gets the service's class
 * instead. Lookups by `Foo::class` resolve through the same map, since Drupal
 * registers interface aliases under the class name.
 *
 * @internal
 */
final class ContainerGetProvider implements MethodReturnTypeProvider
{
    /**
     * @param Closure(Codebase): ServiceIndex $services Returns the index for
     *   the analysis the codebase belongs to. It is rebuilt for each new
     *   analysis, so it is asked for on every call rather than cached here.
     */
    public function __construct(
        private readonly Closure $services,
    ) {}

    public function getTargets(): array
    {
        return [
            // Drupal's container interfaces extend Symfony's, so this covers
            // every Drupal container without touching other PSR-11 ones.
            MethodTarget::exact(Containers::INTERFACE, 'get'),
            MethodTarget::exact('Drupal', 'service'),
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $invocation = $context->invocation;
        $id = ServiceIds::fromArgument($invocation->getArgument(0, 'id'));
        if ($id === null) {
            return null;
        }

        $class = Containers::classFor($context->codebase, ($this->services)($context->codebase)->get($id));
        if ($class === null) {
            return null;
        }

        // A spread could pass any behavior.
        if (Arguments::spreadBefore($invocation, 1)) {
            return null;
        }

        $type = Type::namedObject($class);
        $behavior = $invocation->getArgument(1, 'invalidBehavior', 'invalid_behavior');
        if ($behavior === null) {
            return $type;
        }

        $value = $behavior->type?->getLiteralInt();
        if ($value === null) {
            // A computed behavior could be either. The declared type wins.
            return null;
        }

        // The class is checked against the codebase so an unscanned module
        // cannot inject a name the analyzer would then report as missing.
        return $value === Containers::EXCEPTION_ON_INVALID_REFERENCE ? $type : Type::union($type, Type::null());
    }
}
