<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use Mago\Sdk\Analyzer\PropertyTarget;
use Mago\Sdk\Analyzer\PropertyType;
use Mago\Sdk\Analyzer\PropertyTypeProvider;
use Mago\Sdk\Analyzer\PropertyTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

/**
 * Types the magic field properties of a fieldable entity.
 *
 * `ContentEntityBase::__get()` hands back the field item list for any field
 * name, so `$node->field_thing` reads as a `FieldItemListInterface`. Writing
 * stays `mixed`, because `$node->field_thing = 'x'` is valid Drupal: `__set()`
 * forwards the value to the field's main property.
 *
 * @internal
 */
final class EntityFieldProvider implements PropertyTypeProvider
{
    private const FIELDABLE = 'Drupal\Core\Entity\FieldableEntityInterface';

    private const ENTITY = 'Drupal\Core\Entity\EntityInterface';

    private const FIELD_ITEM_LIST = 'Drupal\Core\Field\FieldItemListInterface';

    public function getTargets(): array
    {
        return [
            PropertyTarget::allProperties(self::FIELDABLE),
            // `EntityBase::__get('original')` works on every entity, config
            // entities included. Drupal 11.2 deprecates it in favor of
            // getOriginal(), and DeprecatedOriginalHook reports each use.
            PropertyTarget::exact(self::ENTITY, 'original'),
        ];
    }

    public function getPropertyType(PropertyTypeProviderContext $context): ?PropertyType
    {
        $access = $context->access;
        if (MagicProperties::declared($context->codebase, $access)) {
            return null;
        }

        if ($access->property === 'original') {
            $original = Type::union(Type::namedObject(self::ENTITY), Type::null());

            return new PropertyType($original, $original);
        }

        return new PropertyType(Type::namedObject(self::FIELD_ITEM_LIST), Type::mixed());
    }
}
