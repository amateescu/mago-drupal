<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use amateescu\MagoDrupal\Analyzer\Checks\ListBuilderCacheabilityCheck;
use Closure;
use Mago\Sdk\Analyzer\CallableSignatureOverride;
use Mago\Sdk\Analyzer\CallableSignatureProviderContext;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\EffectiveCallableSignature;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\CallableParameter;

/**
 * Declares the `$cacheability` parameter core keeps commented out on the
 * list builder operation methods.
 *
 * Core from 11.3 up to 12.0 reads it through `func_get_args()` and passes
 * it on with `parent::getOperations($entity, $cacheability)`, which the
 * declared signature counts as one argument too many. A named
 * `cacheability:` argument fails at runtime while the parameter is commented
 * out, so that call keeps the declared signature. The parameter is untyped:
 * PHP checks no type on an argument without a parameter, and core passes on
 * what it read from `func_get_args()`.
 *
 * @internal
 */
final class ListBuilderOperationsProvider implements MethodReturnTypeProvider, CallableSignatureOverride
{
    /**
     * @param Closure(Codebase): ?string $coreVersion
     */
    public function __construct(
        private readonly Closure $coreVersion,
    ) {}

    public function getTargets(): array
    {
        return [
            MethodTarget::exact('Drupal\Core\Entity\EntityListBuilderInterface', 'getOperations'),
            MethodTarget::exact('Drupal\Core\Entity\EntityListBuilder', 'getDefaultOperations'),
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        return null;
    }

    public function getCallableSignature(CallableSignatureProviderContext $context): ?EffectiveCallableSignature
    {
        foreach ($context->invocation->arguments as $argument) {
            if ($argument->name === 'cacheability') {
                return null;
            }
        }

        if (!ListBuilderCacheabilityCheck::commentedOut(($this->coreVersion)($context->codebase))) {
            return null;
        }

        return new EffectiveCallableSignature([
            new CallableParameter('$entity', Type::namedObject('Drupal\Core\Entity\EntityInterface')),
            new CallableParameter(name: '$cacheability', hasDefault: true),
        ]);
    }
}
