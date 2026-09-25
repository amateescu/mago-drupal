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
 * Types `$storage->read('name')` from the config schema.
 *
 * `StorageInterface::read()` returns the stored data of a config object, or
 * FALSE when nothing is stored under the name. The data has the shape of the
 * whole object. A storage for another collection, such as a language
 * override, holds only part of it, which the shape does not show.
 *
 * @internal
 */
final class ConfigStorageProvider implements MethodReturnTypeProvider
{
    private const STORAGE = 'Drupal\Core\Config\StorageInterface';

    /**
     * @param Closure(Codebase): ConfigSchema $schema
     */
    public function __construct(
        private readonly Closure $schema,
    ) {}

    public function getTargets(): array
    {
        return [MethodTarget::exact(self::STORAGE, 'read')];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $name = $context->invocation->getArgument(0, 'name')?->type?->getLiteralString();
        if ($name === null || $name === '') {
            return null;
        }

        // The schema only vouches for fully validatable configs; the index
        // returns null for the rest.
        $type = ($this->schema)($context->codebase)->typeOf($name, '');

        return $type === null ? null : Type::union($type, Type::false());
    }
}
