<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use amateescu\MagoDrupal\Internal\ConfigSchema;
use Closure;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

/**
 * Types `$config->get('key')` from the config schema.
 *
 * @internal
 */
final class ConfigGetProvider implements MethodReturnTypeProvider
{
    /**
     * @param Closure(Codebase): ConfigSchema $schema
     */
    public function __construct(
        private readonly Closure $schema,
    ) {}

    public function getTargets(): array
    {
        return [MethodTarget::exact(Configs::BASE, 'get')];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $invocation = $context->invocation;
        $name = Configs::nameOf($invocation->receiverType);
        if ($name === null) {
            return null;
        }

        $argument = $invocation->getArgument(0, 'key');
        $key = $argument === null ? '' : $argument->type?->getLiteralString();
        if ($key === null) {
            return null;
        }

        // The schema only vouches for fully validatable configs; the index
        // returns null for the rest. An empty key is the whole object, which
        // is always there; any other key can be absent, so null stays in.
        $type = ($this->schema)($context->codebase)->typeOf($name, $key);
        if ($type === null) {
            return null;
        }

        return $key === '' ? $type : Type::union($type, Type::null());
    }
}
