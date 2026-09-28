<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

/**
 * Types a config entity's `id()` as a string, or null before it has one.
 *
 * `EntityInterface::id()` is `int|string|null` for every entity type. A
 * config entity's ID is its machine name, so passing it to a parameter that
 * takes a string is only a question of the null, not of an integer.
 *
 * @internal
 */
final class ConfigEntityIdProvider implements MethodReturnTypeProvider
{
    public function getTargets(): array
    {
        return [MethodTarget::exact('Drupal\Core\Config\Entity\ConfigEntityInterface', 'id')];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        return Type::union(Type::string(), Type::null());
    }
}
