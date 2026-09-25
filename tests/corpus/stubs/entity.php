<?php

/**
 * @file
 * Entity API signatures the corpus fixtures call.
 */

declare(strict_types=1);

namespace Drupal\Core\Entity {
    interface EntityInterface
    {
        public function id(): string|int|null;

        public function label(): string|null;
    }

    interface FieldableEntityInterface extends EntityInterface
    {
        public function get(string $field_name): \Drupal\Core\Field\FieldItemListInterface;
    }

    interface ContentEntityInterface extends FieldableEntityInterface {}

    interface EntityTypeInterface {}

    interface ContentEntityTypeInterface extends EntityTypeInterface {}

    interface EntityStorageInterface
    {
        public function load(int|string $id): ?EntityInterface;

        public function loadUnchanged(int|string $id): ?EntityInterface;

        /** @return array<int|string, EntityInterface> */
        public function loadMultiple(?array $ids = null): array;

        /** @return array<int|string, EntityInterface> */
        public function loadByProperties(array $values = []): array;

        public function create(array $values = []): EntityInterface;

        public function getQuery(string $conjunction = 'AND'): Query\QueryInterface;

        public function getAggregateQuery(string $conjunction = 'AND'): Query\QueryAggregateInterface;
    }

    interface EntityAccessControlHandlerInterface
    {
        /**
         * Declared the way core did before it documented the conditional
         * return type, so the provider has something to narrow.
         *
         * @return \Drupal\Core\Access\AccessResultInterface|bool
         */
        public function access(EntityInterface $entity, string $operation, ?object $account = null, bool $return_as_object = false);

        /**
         * @return \Drupal\Core\Access\AccessResultInterface|bool
         */
        public function createAccess(?string $entity_bundle = null, ?object $account = null, array $context = [], bool $return_as_object = false);
    }

    interface EntityViewBuilderInterface {}

    interface EntityListBuilderInterface
    {
        public function getOperations(EntityInterface $entity /* , ?\Drupal\Core\Cache\CacheableMetadata $cacheability = null */): array;
    }

    interface EntityHandlerInterface {}

    interface RevisionableStorageInterface extends EntityStorageInterface
    {
        public function loadRevision(int|string $revision_id): ?EntityInterface;

        public function loadRevisionUnchanged(int|string $revision_id): ?EntityInterface;

        /** @return array<int|string, EntityInterface> */
        public function loadMultipleRevisions(array $revision_ids): array;

        public function createRevision(EntityInterface $entity, bool $default = true): EntityInterface;
    }

    interface EntityTypeRepositoryInterface
    {
        public function getEntityTypeFromClass(string $class_name): string;
    }

    interface EntityTypeManagerInterface extends EntityTypeRepositoryInterface
    {
        public function getStorage(string $entity_type_id): EntityStorageInterface;

        public function getAccessControlHandler(string $entity_type_id): EntityAccessControlHandlerInterface;

        public function getViewBuilder(string $entity_type_id): EntityViewBuilderInterface;

        public function getListBuilder(string $entity_type_id): EntityListBuilderInterface;

        public function getFormObject(string $entity_type_id, string $operation): \Drupal\Core\Form\FormInterface;

        public function getHandler(string $entity_type_id, string $handler_type): object;

        public function getDefinition(string $entity_type_id, bool $exception_on_invalid = true): ?EntityTypeInterface;
    }

    interface EntityRepositoryInterface
    {
        public function loadEntityByUuid(string $entity_type_id, string $uuid): ?EntityInterface;

        public function loadEntityByConfigTarget(string $entity_type_id, string $target): ?EntityInterface;

        public function getTranslationFromContext(EntityInterface $entity, ?string $langcode = null, array $context = []): EntityInterface;

        public function getActive(string $entity_type_id, int|string $entity_id, ?array $contexts = null): ?EntityInterface;

        /** @return array<int|string, EntityInterface> */
        public function getActiveMultiple(string $entity_type_id, array $entity_ids, ?array $contexts = null): array;

        public function getCanonical(string $entity_type_id, int|string $entity_id, ?array $contexts = null): ?EntityInterface;

        /** @return array<int|string, EntityInterface> */
        public function getCanonicalMultiple(string $entity_type_id, array $entity_ids, ?array $contexts = null): array;
    }

    class EntityAccessControlHandler implements EntityAccessControlHandlerInterface, EntityHandlerInterface
    {
        public function onlyOnDefaultAccess(): void {}

        public function access(EntityInterface $entity, string $operation, ?object $account = null, bool $return_as_object = false)
        {
            return true;
        }

        public function createAccess(?string $entity_bundle = null, ?object $account = null, array $context = [], bool $return_as_object = false)
        {
            return true;
        }
    }

    class EntityViewBuilder implements EntityViewBuilderInterface, EntityHandlerInterface
    {
        public function onlyOnDefaultViewBuilder(): void {}
    }

    abstract class EntityStorageBase implements EntityStorageInterface
    {
        public function load(int|string $id): ?EntityInterface
        {
            return null;
        }

        public function loadUnchanged(int|string $id): ?EntityInterface
        {
            return null;
        }

        public function loadMultiple(?array $ids = null): array
        {
            return [];
        }

        public function loadByProperties(array $values = []): array
        {
            return [];
        }

        public function create(array $values = []): EntityInterface
        {
            throw new \RuntimeException('stub');
        }

        public function getQuery(string $conjunction = 'AND'): Query\QueryInterface
        {
            return new Query\Query();
        }

        public function getAggregateQuery(string $conjunction = 'AND'): Query\QueryAggregateInterface
        {
            return new Query\QueryAggregate();
        }
    }

    class EntityListBuilder implements EntityListBuilderInterface, EntityHandlerInterface
    {
        public function getOperations(EntityInterface $entity /* , ?\Drupal\Core\Cache\CacheableMetadata $cacheability = null */): array
        {
            return [];
        }

        protected function getDefaultOperations(EntityInterface $entity /* , ?\Drupal\Core\Cache\CacheableMetadata $cacheability = null */): array
        {
            return [];
        }
    }

    abstract class EntityBase implements EntityInterface
    {
        public function __get(string $name): mixed
        {
            return null;
        }

        public function __set(string $name, mixed $value): void {}

        public function __isset(string $name): bool
        {
            return false;
        }

        public function __unset(string $name): void {}
    }

    abstract class ContentEntityBase extends EntityBase implements ContentEntityInterface
    {
        public function id(): string|int|null
        {
            return null;
        }

        public function label(): string|null
        {
            return null;
        }

        public function get(string $field_name): \Drupal\Core\Field\FieldItemListInterface
        {
            throw new \RuntimeException('stub');
        }

        public function __get(string $name): mixed
        {
            return null;
        }

        public function __set(string $name, mixed $value): void {}
    }
}

namespace Drupal\Core\Field {
    interface FieldItemListInterface
    {
        public function onlyOnFieldItemList(): void;

        public function __get(string $name): mixed;

        public function __set(string $name, mixed $value): void;
    }

    interface FieldItemInterface
    {
        public function __get(string $property_name): mixed;

        public function __set(string $property_name, mixed $value): void;
    }
}

namespace Drupal\Core\Entity\Sql {
    class SqlContentEntityStorage extends \Drupal\Core\Entity\EntityStorageBase implements \Drupal\Core\Entity\RevisionableStorageInterface
    {
        public function loadRevision(int|string $revision_id): ?\Drupal\Core\Entity\EntityInterface
        {
            return null;
        }

        public function loadRevisionUnchanged(int|string $revision_id): ?\Drupal\Core\Entity\EntityInterface
        {
            return null;
        }

        public function loadMultipleRevisions(array $revision_ids): array
        {
            return [];
        }

        public function createRevision(\Drupal\Core\Entity\EntityInterface $entity, bool $default = true): \Drupal\Core\Entity\EntityInterface
        {
            return $entity;
        }
    }
}

namespace Drupal\Core\Config\Entity {
    interface ConfigEntityInterface extends \Drupal\Core\Entity\EntityInterface {}

    interface ConfigEntityTypeInterface extends \Drupal\Core\Entity\EntityTypeInterface {}

    interface ConfigEntityStorageInterface extends \Drupal\Core\Entity\EntityStorageInterface {}

    class ConfigEntityStorage extends \Drupal\Core\Entity\EntityStorageBase
    {
        public function onlyOnConfigStorage(): void {}
    }

    abstract class ConfigEntityBase extends \Drupal\Core\Entity\EntityBase implements ConfigEntityInterface
    {
        public function id(): string|int|null
        {
            return null;
        }

        public function label(): string|null
        {
            return null;
        }
    }
}

namespace Drupal\Core\Entity\Attribute {
    /**
     * Mirrors core's parameter order so `handlers` sits at position 12.
     */
    #[\Attribute(\Attribute::TARGET_CLASS)]
    class EntityType
    {
        public function __construct(
            public readonly string $id,
            public readonly ?object $label = null,
            public readonly ?object $label_collection = null,
            public readonly ?object $label_singular = null,
            public readonly ?object $label_plural = null,
            public readonly string $entity_type_class = 'Drupal\Core\Entity\EntityType',
            public readonly string $group = 'default',
            public readonly ?object $group_label = null,
            public readonly bool $static_cache = true,
            public readonly bool $render_cache = true,
            public readonly bool $persistent_cache = true,
            public readonly array $entity_keys = [],
            public readonly array $handlers = [],
        ) {}
    }

    #[\Attribute(\Attribute::TARGET_CLASS)]
    class ContentEntityType extends EntityType {}

    #[\Attribute(\Attribute::TARGET_CLASS)]
    class ConfigEntityType extends EntityType
    {
        public function __construct(
            public readonly string $id,
            public readonly ?object $label = null,
            public readonly ?object $label_collection = null,
            public readonly ?object $label_singular = null,
            public readonly ?object $label_plural = null,
            public readonly ?string $config_prefix = null,
            public readonly string $entity_type_class = 'Drupal\Core\Config\Entity\ConfigEntityType',
            public readonly string $group = 'configuration',
            public readonly ?object $group_label = null,
            public readonly bool $static_cache = false,
            public readonly bool $render_cache = true,
            public readonly array $entity_keys = [],
            public readonly array $handlers = [],
            public readonly array $config_export = [],
        ) {}
    }
}

namespace Drupal\Core\Access {
    interface AccessResultInterface
    {
        public function onlyOnAccessResult(): void;
    }
}

namespace Drupal\Core\Form {
    interface FormInterface
    {
        /**
         * @return array
         */
        public function buildForm(array $form, FormStateInterface $form_state);
    }
}

namespace Drupal\corpus\Entity {
    /**
     * A config entity type that relies on every default handler.
     */
    #[\Drupal\Core\Entity\Attribute\ConfigEntityType(id: 'corpus_setting')]
    class CorpusSetting extends \Drupal\Core\Config\Entity\ConfigEntityBase
    {
        public function onlyOnSetting(): void {}
    }

    interface CorpusThingStorageInterface extends \Drupal\Core\Entity\EntityStorageInterface {}

    class CorpusThingStorage extends \Drupal\Core\Entity\Sql\SqlContentEntityStorage implements CorpusThingStorageInterface
    {
        public function onlyOnThingStorage(): void {}
    }

    class CorpusLegacyStorage extends \Drupal\Core\Entity\Sql\SqlContentEntityStorage
    {
        public function onlyOnLegacyStorage(): void {}
    }

    /**
     * A bare entity type whose storage class exists nowhere Mago can see.
     */
    #[\Drupal\Core\Entity\Attribute\EntityType(id: 'corpus_bare', handlers: ['storage' => 'Drupal\corpus\Missing\Storage'])]
    class CorpusBare implements \Drupal\Core\Entity\EntityInterface
    {
        public function id(): string|int|null
        {
            return null;
        }

        public function label(): string|null
        {
            return null;
        }
    }

    class CorpusThingAccessControlHandler extends \Drupal\Core\Entity\EntityAccessControlHandler
    {
        public function onlyOnThingAccess(): void {}
    }

    class CorpusThingForm implements \Drupal\Core\Form\FormInterface
    {
        public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state)
        {
            return $form;
        }

        public function onlyOnThingForm(): void {}
    }
}

namespace Drupal\Core\Entity\Query {
    /**
     * Core declares the fluent methods with `@return $this` docblocks and no
     * native types; the stub does the same so the corpus proves that path.
     */
    interface ConditionInterface
    {
        /**
         * @param string|ConditionInterface $field
         * @return $this
         */
        public function condition($field, $value = null, $operator = null, $langcode = null);

        /**
         * @return $this
         */
        public function exists($field, $langcode = null);
    }

    interface QueryInterface extends ConditionInterface
    {
        /**
         * @return $this
         */
        public function sort($field, $direction = 'ASC', $langcode = null);

        /**
         * @return $this
         */
        public function range($start = null, $length = null);

        /**
         * @return $this
         */
        public function pager($limit = 10, $element = null);

        /**
         * @return $this
         */
        public function accessCheck($access_check = true);

        /**
         * @return $this
         */
        public function count();

        /**
         * @return $this
         */
        public function allRevisions();

        /**
         * @return $this
         */
        public function latestRevision();

        /**
         * @return ConditionInterface
         */
        public function orConditionGroup();

        /**
         * @return ConditionInterface
         */
        public function andConditionGroup();

        /**
         * @return array<int|string, int|string>|int
         */
        public function execute();
    }

    interface QueryAggregateInterface extends QueryInterface
    {
        /**
         * @return $this
         */
        public function groupBy($field, $langcode = null);

        /**
         * @return list<array<string, mixed>>|int
         */
        public function execute();
    }

    class Condition implements ConditionInterface
    {
        public function condition($field, $value = null, $operator = null, $langcode = null)
        {
            return $this;
        }

        public function exists($field, $langcode = null)
        {
            return $this;
        }
    }

    class Query implements QueryInterface
    {
        public function condition($field, $value = null, $operator = null, $langcode = null)
        {
            return $this;
        }

        public function exists($field, $langcode = null)
        {
            return $this;
        }

        public function sort($field, $direction = 'ASC', $langcode = null)
        {
            return $this;
        }

        public function range($start = null, $length = null)
        {
            return $this;
        }

        public function pager($limit = 10, $element = null)
        {
            return $this;
        }

        public function accessCheck($access_check = true)
        {
            return $this;
        }

        public function count()
        {
            return $this;
        }

        public function allRevisions()
        {
            return $this;
        }

        public function latestRevision()
        {
            return $this;
        }

        public function orConditionGroup()
        {
            return new Condition();
        }

        public function andConditionGroup()
        {
            return new Condition();
        }

        public function execute()
        {
            return [];
        }
    }

    class QueryAggregate extends Query implements QueryAggregateInterface
    {
        public function groupBy($field, $langcode = null)
        {
            return $this;
        }

        public function execute()
        {
            return [];
        }
    }
}

namespace Drupal\user\Entity {
    /**
     * The entity type every Drupal site has; the unknown-id check keys on it.
     */
    #[\Drupal\Core\Entity\Attribute\ContentEntityType(id: 'user')]
    class User extends \Drupal\Core\Entity\ContentEntityBase {}
}

namespace Drupal\user {
    interface RoleStorageInterface extends \Drupal\Core\Config\Entity\ConfigEntityStorageInterface {}
}

namespace Drupal\Core\Language {
    interface LanguageInterface
    {
        public function getId(): string;
    }

    interface LanguageManagerInterface
    {
        /**
         * @return \Drupal\Core\Language\LanguageInterface[]
         */
        public function getLanguages(int $flags = 1): array;
    }
}

namespace Drupal\Core\TypedData {
    interface TranslatableInterface
    {
        /**
         * @return \Drupal\Core\Language\LanguageInterface[]
         */
        public function getTranslationLanguages(bool $include_default = true): array;
    }
}
