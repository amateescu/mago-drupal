# Entities

The analyzer reads the entity types of the codebase, and types what the entity type manager, entity
handlers, entity queries and entity fields hand back. The checks report unknown entity type ids,
entity queries without an access check, uses of the deprecated `original` property, and entity type
definitions and list builders that leave out what core asks for.

## The entity type index

The analyzer indexes every class with a `#[ContentEntityType]`, `#[ConfigEntityType]` or
`#[EntityType]` attribute, from Mago's class metadata. Vendor code is indexed too, so a contrib
workspace sees core's entity types.

Contrib still ships the legacy `@ContentEntityType`, `@ConfigEntityType` and `@EntityType` docblock
annotations, so the analyzer also reads them, from the `src/Entity/*.php` files of the modules under
the Drupal root. See [The plugin index](plugins.md#the-plugin-index) for how a file that an editor
hasn't saved counts. A class that also has the attribute has its annotation ignored, the way
Drupal's discovery does.

Two classes that declare one entity type id cancel each other out, as plugins do. The id is known,
so it isn't reported, but it names no definition.

**Not indexed:**

- a class whose parent chain Mago couldn't resolve;
- an annotation in vendor code outside the Drupal root.

## Handlers and storage

The entity type manager, the storages and the repositories are typed from the entity type that an id
names:

```php
$etm->getStorage('node');                       // Drupal\node\NodeStorage<'node'>
$etm->getStorage('node')->load(1);              // Drupal\node\Entity\Node|null
$etm->getStorage('node')->loadMultiple();       // array<int|string, Drupal\node\Entity\Node>
$etm->getStorage('view')->loadMultiple();       // array<string, Drupal\views\Entity\View>
$etm->getStorage('block_content')->create();    // Drupal\block_content\Entity\BlockContent
$etm->getAccessControlHandler('node');          // Drupal\node\NodeAccessControlHandler<'node'>
$etm->getFormObject('node', 'edit');            // Drupal\node\NodeForm<'node'>
$etm->getDefinition('node');                    // Drupal\Core\Entity\ContentEntityTypeInterface
$etm->getDefinition('node', FALSE);             // Drupal\Core\Entity\ContentEntityTypeInterface|null
$etm->getDefinition($id);                       // Drupal\Core\Entity\EntityTypeInterface
$etm->getHandler($id, 'storage');               // Drupal\Core\Entity\EntityStorageInterface
$typeRepository->getEntityTypeFromClass(Node::class); // 'node'
$storage->getEntityTypeId();                    // 'node'
$repository->loadEntityByUuid('taxonomy_term', $uuid); // Drupal\taxonomy\Entity\Term|null
```

- `getHandler()` for an entity type that the index can't resolve returns what is declared by core on
  the getter of that handler type: `EntityStorageInterface` for `storage`, and the access control,
  view builder and list builder interfaces for `access`, `view_builder` and `list_builder`, since
  `getStorage($id)` is `getHandler($id, 'storage')`. Other handler types keep `object`.
- An id that is one of several literals, such as `'node'|'user'`, gives the union of their handlers
  or definitions. Once one of them is unknown, the getter keeps its declared type.
- `getDefinition()` throws instead of returning null unless its second argument is `FALSE`, so the
  null goes away, even for an entity type id that the index can't resolve. Only a computed second
  argument keeps the declared type.

A handler type has the entity type id as a type parameter, as in `NodeStorage<'node'>`. A later
call on that value reads the parameter, so `->load()` on a `SqlContentEntityStorage` that many
entity types share still knows which entity class comes back. The parameter is trusted only on a
class that the index knows as one of that entity type's handlers. A storage that arrives without
the parameter, for example injected as `EntityStorageInterface`, still resolves when its class
serves exactly one entity type. A receiver that may be either of two storages resolves to neither,
and so does one that may also be a storage of no known entity type.

The default handlers follow core's entity type classes:

- a content entity type gets `SqlContentEntityStorage` and `EntityViewBuilder`;
- a config entity type gets `ConfigEntityStorage`;
- every kind gets `EntityAccessControlHandler`;
- list builders and forms have no default;
- a bare `#[EntityType]` gets no storage default, and its definition isn't narrowed to a definition
  interface.

The storage methods:

- the revisionable storage methods `loadRevision()`, `loadRevisionUnchanged()`,
  `loadMultipleRevisions()` and `createRevision()` are typed as well;
- `loadMultiple()` and `loadByProperties()` key their result by entity id. That is `string` for a
  config entity, whose id is a machine name, as phpstan-drupal types it, and `int|string` for a
  content entity, where phpstan-drupal says `int`. A config entity with a numeric id such as `'1'`
  ends up under an integer key at runtime, which this doesn't cover;
- the `EntityRepositoryInterface` lookups are typed by their entity type id argument, and
  `getTranslationFromContext($entity)` returns the class that it was handed.

## Entity type keys

`EntityTypeInterface::getKey()` is documented as `string|false`. For `id`, `revision`, `bundle`,
`langcode`, `default_langcode` and `revision_translation_affected`, the analyzer types it as
`string`:

- the `EntityType` constructor fills in the last five, with an empty string for the first three,
  and `getKey()` returns what is set, so an absent `revision` key is `''`, not `FALSE`;
- core requires an `id` key of every entity type that can be saved, so `id` is typed as always
  there. A check such as `getKey('id') === FALSE` for an entity type without one, like core's test
  type `entity_test_no_id`, is reported as impossible.

Any other key keeps `string|false`.

```php
$entity_type->getKey('id');                     // string
```

## Access checks

`EntityAccessControlHandlerInterface::access()`, `createAccess()` and `fieldAccess()` return an
`AccessResultInterface` when `$return_as_object` is a literal `TRUE`, and a `bool` otherwise.
Drupal 11.4 says the same with a conditional return type, which is read by Mago, so this matters
only with older core.

```php
$handler->access($entity, 'view');              // bool
$handler->access($entity, 'view', NULL, TRUE);  // Drupal\Core\Access\AccessResultInterface
```

## Keyed lists

Core documents these lists as `EntityTypeInterface[]`, `FieldItemListInterface[]` and so on. The
analyzer keys them by `string`:

- the entity type manager's `getDefinitions()`;
- the entity's `getFields()`, `getTranslatableFields()` and `getFieldDefinitions()`;
- the entity field manager's `getBaseFieldDefinitions()`, `getFieldDefinitions()` and
  `getFieldStorageDefinitions()`.

The keys are entity type ids and field names. A field name matches `/^[_a-z]+[_a-z0-9]*$/`, and no
entity type id is numeric, so PHP never turns one into an integer key, and `array_keys()` or a
`foreach` key hands the name on to a method that takes a string. phpstan-drupal's stubs type the
entity's field methods the same way. The values keep the type documented by core.

`getTranslationLanguages()` and the language manager's `getLanguages()` are keyed by language code,
as `string`, where core documents `LanguageInterface[]`. A language code matches
`LanguageInterface::VALID_LANGCODE_REGEX`, which starts with a letter, so PHP never turns one into
an integer key, and `array_keys($entity->getTranslationLanguages())` hands a language code to
`getTranslation()` without a `less-specific-argument`.

## List builder operations

Core 11.3 up to 12.0 keeps the `$cacheability` parameter of the list builder's `getOperations()` and
`getDefaultOperations()` commented out, and reads it through `func_get_args()`. Its own list
builders pass it on with `parent::getOperations($entity, $cacheability)`. The analyzer declares the
parameter for those calls, so they aren't reported as `too-many-arguments`. A named `cacheability:`
argument fails at runtime on those versions, so that call keeps the declared signature. A list
builder that leaves the parameter out is reported as
[`drupal/list-builder-cacheability`](#drupallist-builder-cacheability).

## Entity queries

`getQuery()`, `getAggregateQuery()`, `\Drupal::entityQuery()` and `\Drupal::entityQueryAggregate()`
return a query tagged with its entity type, its kind, whether access is checked and what
`execute()` returns, as in `QueryInterface<'node', 'content', 'unchecked', 'ids'>`. `accessCheck()`
and `count()` update the tags through the fluent chain, and `execute()` reads them. It returns:

- `int<0, max>` after `count()`;
- `array<int|string, string>` of ids for a content query;
- `array<string, string>` for a config query;
- `list<array<string, mixed>>` for an aggregate query.

A query whose entity type isn't known, from `\Drupal::entityQuery($id)` or from `getQuery()` on a
bare `EntityStorageInterface`, is tagged with an unknown entity type, so the chain is still
tracked. A query that runs without `accessCheck()` is reported as
[`drupal/entity-query-access-check`](#drupalentity-query-access-check).

## Magic entity fields

`ContentEntityBase::__get()` hands back a field item list for any field name, so an undeclared
property on a `FieldableEntityInterface` descendant is typed as
`Drupal\Core\Field\FieldItemListInterface`. `FieldItemList::__get()` forwards to the first item,
and `FieldItemBase::__get()` reads the properties that the field type defines, so an undeclared
property on a field item list or on a field item is accepted as `mixed`. The field type decides
what comes back, and typing it would be a guess.

```php
$node->field_thing;              // Drupal\Core\Field\FieldItemListInterface
$node->field_thing->value;       // mixed, not an undefined property
$item->value;                    // mixed on a FieldItemInterface as well
$node->field_thing = 'a string'; // allowed, the way __set() is
$node->original;                 // Drupal\node\NodeInterface|null, the entity's own type, reported as deprecated
```

Writing a field stays `mixed`, because `$node->field_thing = 'x'` is valid Drupal. A property that
PHP itself resolves keeps its own type: a declared property, an inherited one and a `@property` tag
all win over the field type.

### Field names

`ContentEntityBase::__get()` reads a name that isn't a field from the entity's plain values, which
code uses for ad hoc properties such as `$comment->in_preview` or the `$account->pass_raw` that
tests set, and `__isset()` checks those values. So a name is typed as a field only when some code or
config under the Drupal root defines a field with it, and any other name is `mixed`.
`if ($comment->in_preview)` is then not an always-true condition, and `'x' . $account->pass_raw`
gets Mago's usual report for a `mixed` operand, not one for an object without `__toString()`.

The analyzer reads the field names from every PHP file of the extensions and of `core/tests`:

- string keys given a `BaseFieldDefinition`, `BundleFieldDefinition`, `FieldDefinition` or
  `FieldStorageDefinition`, as in `baseFieldDefinitions()` and the field info hooks;
- `'field_name' => '...'` values, as in `FieldStorageConfig::create()`;
- the literal that a test keeps its field name in, such as `$field_name = 'images'` or
  `$this->fieldName = 'test_text'`, and the first argument of helpers such as
  `createImageField('images', ...)`;
- `installFieldStorageDefinition('...')` calls in update hooks;
- the values of `entity_keys` and `revision_metadata_keys`, whose fields `ContentEntityBase` and
  its traits create.

The file names of the `field.storage.*` and `field.field.*` config in the extensions add theirs.
A name that starts with `field_`, the Field UI's prefix, is a field too, since a site's active
config isn't read.

The names aren't split by entity type, so a field of one entity type is typed as a field on every
entity. That keeps the field type possibly undefined: `isset()`, `empty()` and `??` on such a
property aren't reported as redundant. With no field definition found at all, as in a workspace
without a Drupal root, every lowercase name counts as a field.

These fields are typed as `mixed`:

- a field that some code defines with a name built at runtime, such as `$fields[$name]`;
- a field that a migration creates from its source data;
- a field that only a site's active config has, without the `field_` prefix.

### The original entity

`original` is typed as the entity before the save, not as a field, on config entities too. It has
the entity's own type, as `getOriginal()` returns `?static`. Drupal 11.2 deprecates the magic
property in favor of `getOriginal()` and `setOriginal()`, and its use is reported as
[`drupal/deprecated-original`](#drupaldeprecated-original).

### Referenced entities

`referencedEntities()` is accepted on any field item list, as phpstan-drupal does. phpstan-drupal
types its result as `mixed`, and the analyzer returns what `EntityReferenceFieldItemListInterface`
declares. Only a reference field's list has the method, but `$node->field_tags` and
`$node->get('field_tags')` give the plain `FieldItemListInterface`. On a list of another field type
the call throws, which the types can't tell apart.

### Entity interfaces

When the receiver's static type is an interface, such as `NodeInterface`, Mago reports these
accesses as `missing-magic-method`, since an interface declares no `__get()` or `__set()`. The
analyzer drops that report:

- on any interface that extends `FieldableEntityInterface`. Every content entity class extends
  `ContentEntityBase`, whose magic methods read and write fields and any other name, including the
  `$account->pass_raw` that tests set;
- for `original`, on any entity interface, since `EntityBase` serves that one.

A concrete class without the magic method keeps the report, and so does a `@property` tag on any
other interface.

## Not modelled

These keep the types that the entity type attribute gives:

- `hook_entity_type_build()` and `hook_entity_type_alter()` implementations that swap handlers or
  entity classes;
- a handler class not scanned by Mago, which keeps the declared interface type.

---

## drupal/unknown-entity-type

- **Level:** warning
- **Source:** Drupal's entity type manager

The entity type manager throws a `PluginNotFoundException` for an entity type id that no entity
type declares.

**Reports:**

- a literal entity type id that no indexed entity type declares, passed to a getter of the entity
  type manager.

The getters are `getStorage()`, `getAccessControlHandler()`, `getViewBuilder()`, `getListBuilder()`,
`getFormObject()`, `getHandler()` and `getDefinition()`. A named argument counts, as in
`getHandler(handler_type: 'access', entity_type_id: 'node')`.

**Not reported:**

- `getDefinition($id, FALSE)`, which asks for null instead of an exception;
- a lookup after a `hasDefinition()` call with the same literal id, earlier in the same function.
  Code does this around a lookup of an optional module's entity type, or before it returns early;
- any id while the `user` entity type isn't in the index. Without it, core is outside the analyzed
  code;
- test code (any file under a `tests` directory) and hook documentation (`*.api.php` files).

??? example "Example"

    ```php
    // Reported.
    use Drupal\Core\Entity\EntityStorageInterface;
    use Drupal\Core\Entity\EntityTypeManagerInterface;

    function example_storage(EntityTypeManagerInterface $manager): EntityStorageInterface {
      return $manager->getStorage('usr');
    }

    // Fine.
    use Drupal\Core\Entity\EntityStorageInterface;
    use Drupal\Core\Entity\EntityTypeManagerInterface;

    function example_storage(EntityTypeManagerInterface $manager): EntityStorageInterface {
      return $manager->getStorage('user');
    }
    ```

??? note "Compared with phpstan-drupal"

    phpstan-drupal doesn't report an unknown entity type id.

---

## drupal/entity-query-access-check

- **Level:** error
- **Source:** phpstan-drupal
- **Ports:** phpstan-drupal's `EntityQueryHasAccessCheckRule`

phpstan-drupal reports an entity query that runs without a decision about access checking. Since
Drupal 10 such a query throws. Config entity queries have no access checking, so they are left
alone.

**Reports:**

- `execute()` on an entity query whose chain has no `accessCheck()` call, unless the entity type is
  a known config entity type;
- the same on a query of an unknown entity type.

`accessCheck(FALSE)` is a decision, and isn't reported. `$query->accessCheck(TRUE);` as a statement
of its own counts for the variable, so the fluent chain isn't needed. A chain that ends in it, such
as `$query->condition($group)->accessCheck(TRUE);`, counts for the result of the chain and not for
`$query`, so a later `$query->execute()` is reported. Call `accessCheck()` on the variable itself
there.

**Not reported:**

- a query on a bare `ConfigEntityStorageInterface`, which is a config query of unknown type;
- a query that the analyzer never saw being created. The check reads the tags of the query alone,
  so a query built in one method and run in another is tracked only when the tagged type flows
  through.

??? example "Example"

    ```php
    // Reported.
    function example_active_users(): array {
      return \Drupal::entityQuery('user')->condition('status', 1)->execute();
    }

    // Fine.
    function example_active_users(): array {
      return \Drupal::entityQuery('user')->condition('status', 1)->accessCheck(TRUE)->execute();
    }
    ```

??? note "Compared with phpstan-drupal"

    - phpstan-drupal tracks the queries of `\Drupal::entityQuery()`, `getQuery()` and
      `getAggregateQuery()`. The check also tracks `\Drupal::entityQueryAggregate()`.

---

## drupal/deprecated-original

- **Level:** warning
- **Source:** Drupal's [deprecation of the `original` property](https://www.drupal.org/node/3295826)

Drupal 11.2 deprecates the magic `original` property of an entity in favor of `getOriginal()` and
`setOriginal()`. Drupal 12 removes it.

**Reports:**

- a read, a write, an `isset()` and an `unset()` of `original` on an entity.

**Not reported:**

- an entity class that declares `$original` itself;
- code in a [deprecation scope](deprecations.md#deprecation-scopes), as for Mago's own deprecation
  codes;
- any use while `EntityInterface::getOriginal()` doesn't exist in core, it was added in Drupal 11.2;
- test code (any file under a `tests` directory) and hook documentation (`*.api.php` files).

??? example "Example"

    ```php
    // Reported.
    use Drupal\Core\Entity\EntityInterface;

    function example_label_changed(EntityInterface $entity): bool {
      return $entity->original?->label() !== $entity->label();
    }

    // Fine.
    use Drupal\Core\Entity\EntityInterface;

    function example_label_changed(EntityInterface $entity): bool {
      return $entity->getOriginal()?->label() !== $entity->label();
    }
    ```

??? note "Compared with phpstan-drupal"

    - phpstan-drupal doesn't report the `original` property as deprecated.
    - phpstan-drupal types `original` as a non-null `ContentEntityInterface` on a content entity, so
      PHPStan reports `?->` on it as `nullsafe.neverNull`. On a plain `EntityInterface`, PHPStan
      reports the property as `property.notFound`.

---

## drupal/config-entity-export

- **Level:** error
- **Source:** phpstan-drupal
- **Ports:** phpstan-drupal's `ConfigEntityConfigExportRule`

phpstan-drupal reports a config entity type without a `config_export` key. Without the key,
`ConfigEntityBase::toArray()` throws a `SchemaIncompleteException`, so Drupal can't export the
entity.

**Reports:**

- a config entity type in the entity type index without `config_export`. The type can come from a
  `#[ConfigEntityType]` attribute or a `@ConfigEntityType` annotation.

**Not reported:**

- an abstract class.

??? example "Example"

    ```php title="modules/example/src/Entity/ExampleSetting.php"
    // Reported.
    namespace Drupal\example\Entity;

    use Drupal\Core\Config\Entity\ConfigEntityBase;
    use Drupal\Core\Entity\Attribute\ConfigEntityType;

    #[ConfigEntityType(id: 'example_setting')]
    class ExampleSetting extends ConfigEntityBase {}

    // Fine.
    namespace Drupal\example\Entity;

    use Drupal\Core\Config\Entity\ConfigEntityBase;
    use Drupal\Core\Entity\Attribute\ConfigEntityType;

    #[ConfigEntityType(id: 'example_setting', config_export: ['id', 'label'])]
    class ExampleSetting extends ConfigEntityBase {}
    ```

??? note "Compared with phpstan-drupal"

    - phpstan-drupal checks the `@ConfigEntityType` annotation. The check also checks the
      `#[ConfigEntityType]` attribute.
    - phpstan-drupal looks for `config_export = {` in the text of the docblock. The check reads the
      parsed annotation.

---

## drupal/list-builder-cacheability

- **Level:** error
- **Source:** phpstan-drupal
- **Ports:** phpstan-drupal's `EntityListBuilderOperationsCacheabilityRule`
- **Off with `--core`**

phpstan-drupal reports a list builder's operation method that leaves out the `CacheableMetadata`
parameter added in Drupal 11.3. Core 11.3 up to 12.0 keeps the parameter commented out in the
interface and reads it through `func_get_args()`.

**Reports:**

- a list builder's `getOperations()` or `getDefaultOperations()` method without a second parameter
  typed as `CacheableMetadata`, on core 11.3 up to 12.0. The method is one declared on a class that
  implements `EntityListBuilderInterface`.

**Not reported:**

- core's own `EntityListBuilder`, which reads the parameter through `func_get_args()`;
- a method that the class inherits;
- anything when the core version can't be read from `core/lib/Drupal.php`.

??? example "Example"

    ```php title="modules/example/src/ExampleListBuilder.php"
    // Reported.
    namespace Drupal\example;

    use Drupal\Core\Entity\EntityInterface;
    use Drupal\Core\Entity\EntityListBuilder;

    class ExampleListBuilder extends EntityListBuilder {

      public function getOperations(EntityInterface $entity): array {
        return parent::getOperations($entity);
      }

    }

    // Fine.
    namespace Drupal\example;

    use Drupal\Core\Cache\CacheableMetadata;
    use Drupal\Core\Entity\EntityInterface;
    use Drupal\Core\Entity\EntityListBuilder;

    class ExampleListBuilder extends EntityListBuilder {

      public function getOperations(EntityInterface $entity, ?CacheableMetadata $cacheability = NULL): array {
        return parent::getOperations($entity, $cacheability);
      }

    }
    ```

??? note "Compared with phpstan-drupal"

    - phpstan-drupal reports `getDefaultOperations()` only on a subclass of `EntityListBuilder`. The
      check reports it on any class that implements `EntityListBuilderInterface`.
    - phpstan-drupal reads the core version from the loaded `Drupal` class. The check reads it from
      `core/lib/Drupal.php` under the Drupal root.
