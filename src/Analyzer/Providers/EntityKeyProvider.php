<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

use function in_array;

/**
 * Types `getKey()` as a string for the entity keys every entity type has.
 *
 * Core documents `getKey()` as `string|false`, FALSE for a key the entity
 * type does not define. The `EntityType` constructor fills in `revision`,
 * `bundle` and `langcode` with an empty string and `default_langcode` and
 * `revision_translation_affected` with their own names, so those five are
 * always there, and `getKey()` returns an empty string for an absent one
 * rather than FALSE. Core requires an `id` key of every entity type that can
 * be saved to storage.
 *
 * @internal
 */
final class EntityKeyProvider implements MethodReturnTypeProvider
{
    private const ALWAYS_DEFINED = [
        'id',
        'revision',
        'bundle',
        'langcode',
        'default_langcode',
        'revision_translation_affected',
    ];

    public function getTargets(): array
    {
        return [MethodTarget::exact('Drupal\Core\Entity\EntityTypeInterface', 'getKey')];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $key = $context->invocation->getArgument(0, 'key')?->type?->getLiteralString();

        return $key !== null && in_array($key, self::ALWAYS_DEFINED, strict: true) ? Type::string() : null;
    }
}
