<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Service;

use amateescu\MagoDrupal\Internal\Service\ServiceIndex;
use Closure;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

use function strtolower;

/**
 * Types class resolver lookups.
 *
 * `\Drupal::classResolver()` returns the resolver, and
 * `\Drupal::classResolver(Foo::class)` and
 * `$resolver->getInstanceFromDefinition(Foo::class)` return a `Foo`. Like
 * `ClassResolver`, the definition is tried as a service id first and as a
 * class name second.
 *
 * @internal
 */
final class ClassResolverProvider implements MethodReturnTypeProvider
{
    /**
     * @param Closure(Codebase): ServiceIndex $services Returns the current index.
     */
    public function __construct(
        private readonly Closure $services,
    ) {}

    public function getTargets(): array
    {
        return [
            MethodTarget::exact(Containers::CLASS_RESOLVER, 'getInstanceFromDefinition'),
            MethodTarget::exact('Drupal', 'classResolver'),
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $invocation = $context->invocation;
        $argument = $invocation->getArgument(0, 'definition', 'class');
        // `\Drupal::classResolver()` without an argument returns the resolver
        // itself. Its declared `ClassResolverInterface|object` comes out as
        // `object`, which hides the resolver's methods.
        if ($invocation->arguments === [] && strtolower($invocation->name) === 'classresolver') {
            return Type::namedObject(Containers::CLASS_RESOLVER);
        }

        $definition = ServiceIds::fromArgument($argument);
        if ($definition === null) {
            return null;
        }

        return Containers::typeFor(
            $context->codebase,
            ($this->services)($context->codebase)->get($definition),
            $definition,
        );
    }
}
