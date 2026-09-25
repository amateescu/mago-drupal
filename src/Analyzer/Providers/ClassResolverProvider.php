<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use amateescu\MagoDrupal\Internal\ServiceIndex;
use Closure;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

/**
 * Types class resolver lookups.
 *
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
        $definition = ServiceIds::fromArgument($context->invocation->getArgument(0, 'definition', 'class'));
        if ($definition === null) {
            // `\Drupal::classResolver()` without an argument returns the
            // resolver itself, which the declared type already says.
            return null;
        }

        $class = Containers::classFor(
            $context->codebase,
            ($this->services)($context->codebase)->get($definition),
            $definition,
        );

        return $class === null ? null : Type::namedObject($class);
    }
}
