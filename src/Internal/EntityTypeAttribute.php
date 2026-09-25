<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Metadata\AttributeMetadata;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\KeyedArrayType;

use function array_key_exists;
use function is_string;

/**
 * Reads an entity type declaration off a class's attribute metadata.
 *
 * Handles `#[ContentEntityType]`, `#[ConfigEntityType]` and the base
 * `#[EntityType]`, and fills in the handlers core's entity type classes
 * default when the attribute leaves them out. The `handlers` argument nests
 * one level for `form` operations, hence the branch count.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class EntityTypeAttribute
{
    /**
     * Position of `config_export` in the ConfigEntityType constructor.
     */
    private const CONFIG_EXPORT = 28;

    private const KINDS = [
        'Drupal\Core\Entity\Attribute\ContentEntityType' => EntityTypeKind::Content,
        'Drupal\Core\Entity\Attribute\ConfigEntityType' => EntityTypeKind::Config,
        'Drupal\Core\Entity\Attribute\EntityType' => EntityTypeKind::Base,
    ];

    /**
     * Position of `handlers` in all three attribute constructors.
     */
    private const HANDLERS_POSITION = 12;

    /**
     * What `EntityType`, `ContentEntityType` and `ConfigEntityType` fill in.
     */
    private const DEFAULT_ACCESS = 'Drupal\Core\Entity\EntityAccessControlHandler';

    private const CONTENT_DEFAULTS = [
        'storage' => 'Drupal\Core\Entity\Sql\SqlContentEntityStorage',
        'view_builder' => 'Drupal\Core\Entity\EntityViewBuilder',
    ];

    private const CONFIG_DEFAULTS = [
        'storage' => 'Drupal\Core\Config\Entity\ConfigEntityStorage',
    ];

    private function __construct() {}

    /**
     * Returns the class's entity type, or null when it declares none.
     *
     * @param list<AttributeMetadata> $attributes
     */
    public static function read(string $class, array $attributes): ?EntityTypeDefinition
    {
        $name = Shape::nonEmptyString($class);
        if ($name === null) {
            return null;
        }

        foreach ($attributes as $attribute) {
            $kind = self::KINDS[$attribute->name] ?? null;
            if ($kind === null) {
                continue;
            }

            $id = Shape::nonEmptyString($attribute->getArgument(0, 'id')?->valueType?->getLiteralString());
            if ($id === null) {
                return null;
            }

            return new EntityTypeDefinition(
                $id,
                $name,
                $kind,
                self::withDefaults($kind, self::handlers($attribute)),
                $kind !== EntityTypeKind::Config
                || $attribute->getArgument(self::CONFIG_EXPORT, 'config_export') !== null,
            );
        }

        return null;
    }

    /**
     * Fills in the handlers core's entity type classes default.
     *
     * @param array<non-empty-string, non-empty-string> $handlers
     * @return array<non-empty-string, non-empty-string>
     */
    public static function withDefaults(EntityTypeKind $kind, array $handlers): array
    {
        $defaults = match ($kind) {
            EntityTypeKind::Content => self::CONTENT_DEFAULTS,
            EntityTypeKind::Config => self::CONFIG_DEFAULTS,
            EntityTypeKind::Base => [],
        };

        return [...$defaults, 'access' => self::DEFAULT_ACCESS, ...$handlers];
    }

    /**
     * Flattens the `handlers` argument; `form` operations become `form.<op>`.
     *
     * @return array<non-empty-string, non-empty-string>
     */
    private static function handlers(AttributeMetadata $attribute): array
    {
        $handlers = [];
        foreach (self::items($attribute->getArgument(
            self::HANDLERS_POSITION,
            'handlers',
        )?->valueType) as $type => $value) {
            $class = Shape::nonEmptyString($value->getLiteralString());
            if ($class !== null) {
                $handlers[$type] = $class;
                continue;
            }

            foreach (self::items($value) as $operation => $nested) {
                $nestedClass = Shape::nonEmptyString($nested->getLiteralString());
                if ($nestedClass !== null) {
                    $handlers[$type . '.' . $operation] = $nestedClass;
                }
            }
        }

        return $handlers;
    }

    /**
     * The string-keyed items of a literal array type.
     *
     * @return array<non-empty-string, Type>
     */
    private static function items(?Type $type): array
    {
        $items = [];
        foreach ($type === null ? [] : $type->atomicTypes as $atomic) {
            if (!$atomic instanceof KeyedArrayType) {
                continue;
            }

            foreach ($atomic->knownItems ?? [] as $item) {
                $key = $item->key->value;
                if (is_string($key) && $key !== '' && !array_key_exists($key, $items)) {
                    $items[$key] = $item->type;
                }
            }
        }

        return $items;
    }
}
