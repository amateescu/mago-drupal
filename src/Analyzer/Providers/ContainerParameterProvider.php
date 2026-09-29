<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use amateescu\MagoDrupal\Internal\ServiceParameters;
use Closure;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

/**
 * Types container parameter reads from the services files.
 *
 * `getParameter()` is declared `array|bool|string|int|float|UnitEnum|null`.
 * A literal name that a `parameters:` section defines gets the kind of value
 * written there instead: `string` for `app.root`, `bool` for
 * `security.enable_super_user`.
 *
 * @internal
 */
final class ContainerParameterProvider implements MethodReturnTypeProvider
{
    /**
     * @param Closure(Codebase): ServiceParameters $parameters Returns the
     *   parameter kinds for the analysis the codebase belongs to.
     */
    public function __construct(
        private readonly Closure $parameters,
    ) {}

    public function getTargets(): array
    {
        // Drupal's container interfaces extend Symfony's, and so does the
        // return type of `\Drupal::getContainer()`.
        return [MethodTarget::exact(Containers::INTERFACE, 'getParameter')];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $name = $context->invocation->getArgument(0, 'name')?->type?->getLiteralString();

        return $name === null ? null : ($this->parameters)($context->codebase)->type($name);
    }
}
