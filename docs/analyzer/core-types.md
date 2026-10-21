# Core types and stubs

How the analyzer types the core methods documented by Drupal differently from how they behave,
through type providers and through stub files.

## Core return values

The `drupal` plugin types a few more of core's methods the way the code behaves:

```php
$setting->id();                          // string|null on a config entity, not int|string|null
$workspace->id();                        // string|null on a content entity with a string ID field
$storage->loadMultipleRevisions([$a, $b]); // a list of int|string IDs is accepted
$queue->claimItem();                     // (stdClass&object{data: mixed, item_id: int|string, created: int|string, ...})|false
$file_system->scanDirectory($dir, $mask); // array<int|string, stdClass&object{uri: string, filename: string, name: string, ...}>
$this->getTestFiles('image');            // list<stdClass&object{uri: string, filename: string, name: string, ...}>
$installer->validateUninstall(['mod']);  // array<string, list<string|MarkupInterface>>
static::getEntityTypeEvents();           // array<string, list<array{0: string, 1: int}>>
parent::create($container);              // static, in a form or controller
$manager->createHandlerInstance(X::class, $type); // X, not object
$query->addWhere(0, $field, $value);      // the group may be an int
$url->toString();                        // string
$url->toString(TRUE);                    // Drupal\Core\GeneratedUrl
$url->toString($flag);                   // Drupal\Core\GeneratedUrl|string
```

- **Config entity IDs.** A config entity's ID is its machine name, so a caller that passes one on
  only has to handle the null.
- **Content entity IDs.** A content entity type's ID is an integer, unless its
  `baseFieldDefinitions()` creates the ID field (`$fields['id']` or
  `$fields[$entity_type->getKey('id')]`) as a `string` or `uuid` field, as `Workspace` does. An
  integer ID comes back from storage as a numeric string, so those keep `int|string|null`. The
  method is read up through `parent::baseFieldDefinitions()` calls, and an interface is narrowed
  when every entity class that implements it has a string ID.
- **ID lists.** The repository, revision storage and workspace methods that document an ID list as
  `int[]|string[]` accept `array<int|string>`. The IDs of one entity type are all integers or all
  strings, but a list built from `id()` calls is typed `list<int|string>`. The rest of each
  signature is read from core's own declaration.
- **Queue items.** `claimItem()` is documented as `bool|object` and never returns TRUE. The
  properties of the item come from the description of the method, and the database queue reads
  `item_id` and `created` as strings. Core's queues build the item as a `stdClass`, and the type
  says so on `QueueInterface` and on core's queue classes, since Mago takes an object shape alone
  for something that can never be one. A queue outside core that returns an item of its own class
  through the interface gets an `impossible-condition` on `instanceof`. A queue class that declares
  its own `claimItem()` keeps what it declares.
- **Scanned files.** The files that `scanDirectory()` and `getTestFiles()` find are the objects
  their descriptions list, built as a `stdClass`, which is what `uploadNodeImage()` in core's image
  tests takes. Core's `previewNodeImage()` documents an `ImageInterface` for the same files, so
  those calls are reported as `possibly-invalid-argument`: the docblock is wrong, not the call.
- **Views query groups.** Views' `Sql::addWhere()`, `addWhereExpression()` and
  `addHavingExpression()` document `$group` as a string, and tell the caller to use 0 for the
  default group. The groups are array keys, so the parameter takes `int|string`. The other
  parameters keep their declared types.
- **Uninstall reasons.** Uninstall validators return translatable markup, which keeps their
  placeholders safe to render. The `module-uninstall-validator.stub` accepts that on each validator.
- **Event lists.** `EntityTypeEventSubscriberTrait` and `FieldStorageDefinitionEventSubscriberTrait`
  build a list of `[method, priority]` pairs per event, which a subscriber can return as it is.
- **Handler instances.** `createHandlerInstance()` builds an instance of the class that it is
  handed, so a literal class name or a `class-string<X>` gives that type. A plain string keeps
  core's `object`.
- **URLs.** `Url::toString()` is documented as `string|GeneratedUrl`, and its argument decides which
  one comes back. A stub could say the same, but the descendants of a stubbed class inherit only
  what the stub declares, so a module's own subclass of `Url` would lose every other method.
- **`create()`.** `ContainerInjectionInterface::create()` returns a new instance of the called
  class, but doesn't document a return type. Mago gives a `create()` that only says `{@inheritdoc}`
  that interface's missing type, even where a parent has the `@return static` of `AutowireTrait`. So
  `$instance = parent::create($container)` in a form or controller is `static`, and so is
  `Foo::create($container)` when no `create()` on the way documents a type.

`getLoadedRevisionId()` stays the `int` documented by core, though it is NULL on a new or duplicated
entity. With the null in, Mago reports every `==` and `!=` comparison with it, and the flag that
stops null reports elsewhere doesn't reach comparisons.

## Inspector assertions

An `Inspector::assert*()` call that is true, as inside `assert()`, narrows the value that it checks,
as phpstan-drupal does. Each type is what the method tests:

- `assertAllStrings($names)` gives `iterable<mixed, string>`;
- `assertAllNumeric()` counts numeric strings, since it uses `is_numeric()`;
- `assertStrictArray()` gives a list;
- `assertStringable($label)` gives `string|\Stringable`;
- `assertAllObjects($items, Foo::class)` gives an iterable of `Foo`;
- `assertAll()` takes any callable, so it only makes a `mixed` value iterable.

An assertion that the type already holds is reported as `redundant-type-comparison`.

phpstan-drupal narrows `assertAllNumeric()` to an iterable of `int|float`, so it reports the
assertion on numeric strings as always false. It narrows `assertStrictArray()` to an array with
non-negative int keys. It doesn't narrow with `assertStringable()`.

## Stub files

The worker loads the stub files under `resources/stubs/` into Mago's symbol table before the
analysis starts. They are read for symbols only, and never linted, formatted or reported on.

```php
$cache->get('cid');    // object{cid: string, data: mixed, created: int|float|numeric-string, expire: int|numeric-string, tags: list<string>, valid: bool, ...}|false
foreach ($entity as $name => $items) {}   // string => Drupal\Core\Field\FieldItemListInterface
$entity->isRevisionTranslationAffected(); // bool|int|string|null
$validator->validate('module');           // array<string|MarkupInterface>
$this->t('@name', ['@name' => $label]);   // reported when $label can be NULL
$permissions->getPermissions()[$name]['title']; // TranslatableMarkup|string
```

- **Content entity fields.** A `foreach` over a `ContentEntityInterface` gives its field item lists,
  keyed by field name, as `ContentEntityBase::getIterator()` builds them. Core doesn't name a value
  type for the `\Traversable` that the interface extends, so the loop variables are `mixed` without
  the stub.
- **Revision translation flag.** Core documents `isRevisionTranslationAffected()` as returning a
  bool, but `ContentEntityBase` returns the raw field value: `'1'` or `'0'` from MySQL, an int from
  SQLite, or NULL when the flag isn't set. A `(bool)` cast on it is needed, so it isn't reported as
  `redundant-cast`.
- **Translation placeholders.** The placeholder arguments of `t()`, `$this->t()`,
  `formatPlural()`, `TranslationInterface::translate()` and the `FormattableMarkup`,
  `TranslatableMarkup` and `PluralTranslatableMarkup` constructors are typed as
  `array<string, scalar|\Stringable>`, as phpstan-drupal 2.2.2 types them. Core hands every value to
  `Html::escape()`, which takes a string, so NULL, an array or an object without `__toString()`
  throws a TypeError. Mago reports a value that can be one of those as `possibly-invalid-argument`,
  and a `mixed` one as `less-specific-nested-argument-type`.
- **Permission titles.** Core documents the title of a permission as a string, but
  `PermissionHandler` wraps the title and the description of every permission from a
  `*.permissions.yml` file in `t()`, and the title from a permission callback is whatever the
  callback returns. So the title is `TranslatableMarkup|string`.
- **Entity IDs.** `id()` and `uuid()` of `EntityInterface` only read what the entity holds, so the
  stub marks them `@psalm-mutation-free`. Mago then keeps what a check on one call shows for the
  later calls on the same entity, up to a call that can change it, such as `save()`. After
  `if ($entity->id() === NULL) { throw ...; }` or `isset($labels[$entity->uuid()])`, the next call
  isn't null. This holds for a receiver typed as `EntityInterface` or one of its interfaces, such as
  `ContentEntityInterface`. An entity class such as `Node` declares its own `id()`, which isn't
  marked. PHPStan remembers what a check on any method call shows, on a class too, and keeps it
  across `save()`.

A stub only wins where core is a dependency. When core itself is the analyzed code, Mago keeps
core's own declaration, so a run on core doesn't see a change.

### How a stub replaces a class

A stub replaces the class header: `extends`, `implements` and the class constants come from the
stub. The stubbed class keeps the members of core's declaration that the stub leaves out, but its
descendants inherit only the members that the stub declares, and a stubbed interface or trait is
the stub alone. So a stub restates the whole header, and every member when the type is an
interface, a trait or a class with subclasses. A restated method's docblock replaces core's too,
`@deprecated` included, so the stubs repeat core's parameter types and deprecations. Each file
changes one or two members, and the rest are there to keep the type intact.

### Core releases

A stub can follow the installed core release:

- `@stub-deprecated-in 11.2` on a member drops its `@deprecated` tag on a core older than 11.2;
- `@stub-removed-in 12.0` drops the member on 12.0 and later;
- `@stub-added-in 11.3` drops the member on a core older than 11.3.

So a stub doesn't claim a deprecation or a method that doesn't exist in the installed core. The
release is read from the root's `Drupal::VERSION`. With no core on disk, the stub is used as
written.

## Not ported from phpstan-drupal

Not translated from phpstan-drupal's stub directory:

- the generics of the `TypedData` and `FieldItemList` hierarchies, about 60 files, most of them
  empty class bodies that only fix the order in which the inheritance is read by PHPStan;
- the `@property` docblocks of the field item classes. Each pins a parent class moved by core
  between releases, as `EntityReferenceItem` already extends `EntityReferenceItemBase` in 11.4, and
  they add warnings on concretely typed items;
- the conditional returns of `AccessResult::allowedIf()` and `forbiddenIf()`. A condition that isn't
  a literal gives the union, and `AccessResultAllowed` doesn't implement
  `AccessResultReasonInterface`, so `allowedIf($x)->setReason()` becomes an error in the contrib
  code that calls it that way;
- `$modules` on `KernelTestBase`. Restating that class header drops the PHPUnit ancestry that the
  test checks and every assertion depend on. Core's `BrowserTestBase` already documents `$modules`
  as `string[]`;
- whatever is already done by the plugin's own providers: entity storage, entity queries, container
  lookups, config, plugin managers and `getDefinition()`;
- about 30 stubs that only restate a class or interface header, so that PHPStan reads the
  inheritance in the right order. Mago doesn't need them;
- `twig_escape_filter()`. Twig 3 deprecates the function, and Mago reports a call to it as
  deprecated, which is right for the Twig that core ships;
- `@phpstan-impure` on `LockBackendInterface::acquire()`. Mago remembers a result from one call to
  the next only for a mutation-free method, and `acquire()` isn't one, so a second `acquire()` after
  `wait()` isn't taken to return what the first one did.

Not ported from phpstan-drupal's type extensions:

- narrowing one method's result by another call on the same object: `id()` isn't null once `isNew()`
  is false, and `getKey()` or `getLinkTemplate()` isn't false once `hasKey()` or `hasLinkTemplate()`
  is true for the same key. A provider's assertion narrows a variable or a parameter, not what
  another method returns. A docblock assertion can name `$this->id()`, but Mago reads it both ways:
  `@psalm-assert-if-false !null $this->id()` on `isNew()` would also make `id()` null whenever
  `isNew()` is true, which is wrong after `enforceIsNew()`. A call with arguments, such as
  `getKey('owner')`, can't be named at all. `getKey()` is a string for the keys that every entity
  type has, and the plugin drops the reports about a possible `false` from a `getKey()` or
  `getLinkTemplate()` call that a `hasKey()` or `hasLinkTemplate()` check guards, see
  [Entity type keys](entities.md#entity-type-keys);
- the `entity-type-id` docblock type.
