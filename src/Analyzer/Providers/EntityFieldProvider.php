<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use Mago\Sdk\Analyzer\PropertyTarget;
use Mago\Sdk\Analyzer\PropertyType;
use Mago\Sdk\Analyzer\PropertyTypeProvider;
use Mago\Sdk\Analyzer\PropertyTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\TypeFlags;

use function preg_match;

/**
 * Types the magic field properties of a fieldable entity.
 *
 * `ContentEntityBase::__get()` hands back the field item list for any field
 * name, so `$node->field_thing` is typed as a `FieldItemListInterface`.
 * Writing stays `mixed`, because `$node->field_thing = 'x'` is valid Drupal:
 * `__set()` forwards the value to the field's main property.
 *
 * Any other name reads the entity's plain values, which code uses for ad hoc
 * flags such as `in_preview`, and `__isset()` checks those. So the field type
 * is marked as possibly undefined, which keeps `isset()`, `empty()` and `??`
 * meaningful, and a name no field can have, such as `passRaw`, is typed as
 * `mixed`.
 *
 * @internal
 */
final class EntityFieldProvider implements PropertyTypeProvider
{
    private const FIELDABLE = 'Drupal\Core\Entity\FieldableEntityInterface';

    private const ENTITY = 'Drupal\Core\Entity\EntityInterface';

    private const FIELD_ITEM_LIST = 'Drupal\Core\Field\FieldItemListInterface';

    /**
     * Field names are lowercase letters, digits and underscores.
     */
    private const FIELD_NAME = '/^[a-z_][a-z0-9_]*$/';

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

        if (preg_match(self::FIELD_NAME, $access->property) !== 1) {
            return new PropertyType(Type::mixed(), Type::mixed());
        }

        $field = Type::namedObject(self::FIELD_ITEM_LIST)->withFlags(new TypeFlags(possiblyUndefined: true));

        return new PropertyType($field, Type::mixed());
    }
}
