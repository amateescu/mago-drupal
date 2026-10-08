<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use Mago\Sdk\Analyzer\PropertyTarget;
use Mago\Sdk\Analyzer\PropertyType;
use Mago\Sdk\Analyzer\PropertyTypeProvider;
use Mago\Sdk\Analyzer\PropertyTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

/**
 * Accepts the magic properties of a field item and of a field item list.
 *
 * `FieldItemBase::__get()` reads a property the field type defines, and
 * `FieldItemList::__get()` reads it off the first item, so `$item->value`
 * and `$node->field_thing->value` are valid for any field. The field type
 * decides what comes back, so the type stays `mixed`; the point is that Mago
 * stops reporting the access as an undefined property.
 *
 * @internal
 */
final class FieldItemPropertyProvider implements PropertyTypeProvider
{
    private const FIELD_ITEM = 'Drupal\Core\Field\FieldItemInterface';

    private const FIELD_ITEM_LIST = 'Drupal\Core\Field\FieldItemListInterface';

    public function getTargets(): array
    {
        return [
            PropertyTarget::allProperties(self::FIELD_ITEM),
            PropertyTarget::allProperties(self::FIELD_ITEM_LIST),
        ];
    }

    public function getPropertyType(PropertyTypeProviderContext $context): ?PropertyType
    {
        if (MagicProperties::declared($context->codebase, $context->access)) {
            return null;
        }

        return new PropertyType(Type::mixed(), Type::mixed());
    }
}
