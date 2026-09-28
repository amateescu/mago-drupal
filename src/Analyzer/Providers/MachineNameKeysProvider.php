<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

use function strtolower;

/**
 * Keys the entity type and field lists core returns by machine name, typed as
 * strings.
 *
 * Core documents these as `EntityTypeInterface[]`, `FieldItemListInterface[]`
 * and so on, keyed by entity type ID or field name. A field name matches
 * `/^[_a-z]+[_a-z0-9]*$/`, and entity type IDs are machine names no entity
 * type makes numeric, so PHP never turns one into an integer key. With string
 * keys, `array_keys()` and `foreach` hand the name on to the methods that
 * take one. phpstan-drupal's stubs type the entity's field methods the same
 * way. Only the keys change; the values keep the type core documents.
 *
 * @internal
 */
final class MachineNameKeysProvider implements MethodReturnTypeProvider
{
    private const ENTITY_TYPE_MANAGER = 'Drupal\Core\Entity\EntityTypeManagerInterface';

    private const FIELDABLE_ENTITY = 'Drupal\Core\Entity\FieldableEntityInterface';

    private const FIELD_MANAGER = 'Drupal\Core\Entity\EntityFieldManagerInterface';

    /**
     * The value type of each method's list, keyed by lowercase method name,
     * which is how the host reports names.
     */
    private const VALUES = [
        'getdefinitions' => 'Drupal\Core\Entity\EntityTypeInterface',
        'getfields' => 'Drupal\Core\Field\FieldItemListInterface',
        'gettranslatablefields' => 'Drupal\Core\Field\FieldItemListInterface',
        'getfielddefinitions' => 'Drupal\Core\Field\FieldDefinitionInterface',
        'getbasefielddefinitions' => 'Drupal\Core\Field\FieldDefinitionInterface',
        'getfieldstoragedefinitions' => 'Drupal\Core\Field\FieldStorageDefinitionInterface',
    ];

    public function getTargets(): array
    {
        return [
            MethodTarget::exact(self::ENTITY_TYPE_MANAGER, 'getDefinitions'),
            MethodTarget::exact(self::FIELDABLE_ENTITY, 'getFields'),
            MethodTarget::exact(self::FIELDABLE_ENTITY, 'getTranslatableFields'),
            MethodTarget::exact(self::FIELDABLE_ENTITY, 'getFieldDefinitions'),
            MethodTarget::exact(self::FIELD_MANAGER, 'getBaseFieldDefinitions'),
            MethodTarget::exact(self::FIELD_MANAGER, 'getFieldDefinitions'),
            MethodTarget::exact(self::FIELD_MANAGER, 'getFieldStorageDefinitions'),
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $value = self::VALUES[strtolower($context->invocation->name)] ?? null;

        return $value === null ? null : Type::array(Type::string(), Type::namedObject($value));
    }
}
