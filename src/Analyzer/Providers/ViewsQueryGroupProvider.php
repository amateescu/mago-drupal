<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use Mago\Sdk\Analyzer\CallableSignatureOverride;
use Mago\Sdk\Analyzer\CallableSignatureProviderContext;
use Mago\Sdk\Analyzer\EffectiveCallableSignature;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\CallableParameter;

/**
 * Lets the group of a Views SQL condition be an integer.
 *
 * Core documents `$group` as a string on `addWhere()`, `addWhereExpression()`
 * and `addHavingExpression()`, and the same docblock says to use 0 for the
 * default group. The groups are array keys, so 0 and '0' are one group. The
 * other parameters keep what the method declares.
 *
 * @internal
 */
final class ViewsQueryGroupProvider implements MethodReturnTypeProvider, CallableSignatureOverride
{
    private const SQL = 'Drupal\views\Plugin\views\query\Sql';

    public function getTargets(): array
    {
        return [
            MethodTarget::exact(self::SQL, 'addWhere'),
            MethodTarget::exact(self::SQL, 'addWhereExpression'),
            MethodTarget::exact(self::SQL, 'addHavingExpression'),
        ];
    }

    /**
     * The return type stays the declared one.
     */
    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        return null;
    }

    public function getCallableSignature(CallableSignatureProviderContext $context): ?EffectiveCallableSignature
    {
        $method = $context->codebase->getDeclaringMethod(self::SQL, $context->invocation->name);
        $declared = $method === null ? [] : $method->parameters;
        if ($declared === []) {
            return null;
        }

        $parameters = [];
        foreach ($declared as $position => $parameter) {
            $flags = $parameter->flags;
            $parameters[] = new CallableParameter(
                name: $parameter->name,
                type: $position === 0
                    ? Type::union(Type::int(), Type::string())
                    : $parameter->type->type ?? $parameter->declaredType?->type,
                byReference: $flags->contains(MetadataFlags::BY_REFERENCE),
                variadic: $flags->contains(MetadataFlags::VARIADIC),
                hasDefault: $flags->contains(MetadataFlags::HAS_DEFAULT),
            );
        }

        return new EffectiveCallableSignature($parameters);
    }
}
