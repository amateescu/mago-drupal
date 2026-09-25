# Analyzer plugin

The `drupal` analyzer plugin is registered by the shipped worker and enabled by default. It
contributes return types the analyzer cannot infer from PHP alone.

The worker registers a second plugin, `phpunit`, also enabled by default. It covers PHPUnit and
Prophecy and depends on nothing Drupal-specific: [test assertions](#test-assertions),
[mock unions](#mock-unions) and [Prophecy calls](#prophecy-calls), each with a check for the
docblocks that type a double wrong. With
`disable-default-plugins = true`, list both in `[analyzer] plugins` to keep them.

A third plugin, `phpstan-ignores`, is off by default: it lets `@phpstan-ignore` comments drop the
Mago issues that report the same finding, see [PHPStan ignores](#phpstan-ignores).

## Container lookups

`$container->get('id')` is declared `?object`. The plugin replaces it with the class the container
hands back when the id is a string literal or a `Foo::class` constant. It covers every
implementation of Symfony's `ContainerInterface`, so Drupal's container interfaces,
`ContainerBuilder` and the `$this->container` of kernel tests all resolve, and so does
`\Drupal::service()`. A variable typed only as PSR-11's `ContainerInterface` is left alone, since
other libraries implement that interface for containers of their own.

```php
$container->get('entity_type.manager');            // Drupal\Core\Entity\EntityTypeManager
$container->get(EntityTypeManagerInterface::class); // same, through the interface alias
$container->get('cache.default');                   // Drupal\Core\Cache\CacheBackendInterface
$container->get('kernel');                          // Drupal\Core\DrupalKernelInterface
$container->get('no.such.service');                 // stays ?object
```

A literal second argument other than `EXCEPTION_ON_INVALID_REFERENCE` keeps `null` in the type,
since the container returns null for a missing service under every other behavior. A computed
second argument leaves the declared type alone.

`\Drupal::classResolver('id')` and `ClassResolverInterface::getInstanceFromDefinition('id')` try the
argument as a service id first and as a class name second, the order `ClassResolver` uses.

`has()` is left alone on purpose. A services file on disk means the module exists, not that it is
installed, so `if ($container->has('optional.service'))` has to stay a real branch.

A lookup of a service whose definition carries `deprecated:` is reported as
`drupal/deprecated-service` with the message from the definition. This covers `get()`,
`\Drupal::service()` and the class resolver; `has()` is how code probes without instantiating, so it
is not reported. A string id that no services file or provider defines is reported as
`drupal/unknown-service`, once core's own services are in the index. So is a private one: a
`public: false` service, set on it, inherited from its `parent:` or from the file's `_defaults`, and
the `.inner` id a decorator moves a service to. The compiled container leaves those out, so `get()`
fails the same way. An alias always works, so an alias of a private service is fine. A `Foo::class`
id is not checked, since the container registers hook classes and other autowired services under
their class name without a YAML line, and neither is a lookup passing a behavior other than
`EXCEPTION_ON_INVALID_REFERENCE`, which asks for null on purpose. Test code, any file under a
`tests` directory, and hook documentation in `*.api.php` files are left alone for both: tests
exercise deprecated services on purpose and build their own containers, and hook documentation
uses made-up ids.

## Where the service index comes from

The worker reads two sources without booting Drupal:

- **`*.services.yml`** of core and every module and profile under the Drupal root. A services
  file counts when a `*.info.yml` of the same name sits next to it, which skips scaffold
  templates and test fixture copies. `alias:`, the `'@id'` shorthand, `parent:` inheritance
  (the parent's `deprecated:` included), `abstract:` templates, the class-as-id shorthand and
  `deprecated:` messages are resolved the way the container compiler resolves them. The synthetic
  `kernel`, `class_loader` and `service_container` services are added as well.
- **`*ServiceProvider.php`** classes in the analyzed `[source] paths`, read through Mago's codebase
  scan; the provider files under the root's `core/lib` and extension `src` directories are also read
  off disk for the ids they register, so a core service that only exists in PHP counts as defined in
  a contrib workspace too, with its type left alone. `$container->register('id', Foo::class)`,
  `->register('id')->setClass(...)`, `->setDefinition('id', new Definition(Foo::class))` and
  `->setAlias('alias', 'id')` are picked up when their arguments are literals. A literal id whose
  class is computed is kept as a service of unknown type, so the id counts as defined. Provider
  registrations override YAML ones, which is the order the container applies them in.

`decorates:` is applied the way the container compiler applies it. The decorated id returns the
decorator's class, and so does every alias of the id. What the id pointed at before is reachable as
`<decorator id>.inner` or the `decoration_inner_name`. When several services decorate one id, the
highest `decoration_priority` is applied first and the id returns the lowest one; between equal
priorities, the last one defined wins. A decorator of a missing id is dropped with
`decoration_on_invalid: ignore` and takes the id over with `decoration_on_invalid: ~`. A decorator
from a module the run does not analyze is not in the codebase, and the id then keeps the class it
had: one contrib module decorating a core service would otherwise turn every core caller of that id
into a bare `object`.

Not indexed, so the declared type stays: services whose class only exists at runtime (a `factory:`
without `class:`, a `%parameter%` class), services whose class Mago has not scanned, and anything a
`ServiceModifierInterface::alter()` changes.

## Entity types

Every class carrying `#[ContentEntityType]`, `#[ConfigEntityType]` or `#[EntityType]` is indexed
from Mago's class metadata. Vendor code is indexed too, so a contrib workspace sees core's entity
types. Legacy `@ContentEntityType`, `@ConfigEntityType` and `@EntityType` docblock annotations are
read too, since contrib still ships them, but only from `src/Entity/*.php` files under the analyzed
paths; the attribute path has no such limit. A class carrying the attribute has its annotation
ignored, the way Drupal's discovery does. Not read: classes whose parent chain Mago could not
resolve, and annotations in vendor code, which a scan hook never sees.

```php
$etm->getStorage('node');                       // Drupal\node\NodeStorage<'node'>
$etm->getStorage('node')->load(1);              // Drupal\node\Entity\Node|null
$etm->getStorage('node')->loadMultiple();       // array<int|string, Drupal\node\Entity\Node>
$etm->getStorage('block_content')->create();    // Drupal\block_content\Entity\BlockContent
$etm->getAccessControlHandler('node');          // Drupal\node\NodeAccessControlHandler<'node'>
$etm->getFormObject('node', 'edit');            // Drupal\node\NodeForm<'node'>
$etm->getDefinition('node');                    // Drupal\Core\Entity\ContentEntityTypeInterface
$etm->getDefinition('node', FALSE);             // Drupal\Core\Entity\ContentEntityTypeInterface|null
$etm->getDefinition($id);                       // Drupal\Core\Entity\EntityTypeInterface
$etm->getEntityTypeFromClass(Node::class);      // 'node'
$handler->access($entity, 'view');              // bool
$handler->access($entity, 'view', NULL, TRUE);  // Drupal\Core\Access\AccessResultInterface
$storage->getEntityTypeId();                    // 'node'
$repository->loadEntityByUuid('taxonomy_term', $uuid); // Drupal\taxonomy\Entity\Term|null
```

`EntityAccessControlHandlerInterface::access()`, `createAccess()` and `fieldAccess()` return an
`AccessResultInterface` when `$return_as_object` is a literal true and a `bool` otherwise. Drupal
11.4 says the same with a conditional return type, which Mago already reads, so this only matters
against older core.

A handler type carries the entity type id as a type parameter. A later call on that value reads the
tag, so `->load()` on a `SqlContentEntityStorage` shared by many entity types still knows which
entity class comes back. The tag is trusted only on a class the index knows as one of that entity
type's handlers. A storage that arrives untagged, for example injected as `EntityStorageInterface`,
still resolves when its class serves exactly one entity type. A receiver that may be either of two
storages resolves to neither, and so does one that may also be a storage of no known entity type.

Defaults follow core's entity type classes: content entity types get `SqlContentEntityStorage` and
`EntityViewBuilder`, config entity types get `ConfigEntityStorage`, every kind gets
`EntityAccessControlHandler`; list builders and forms have no default. A bare `#[EntityType]` gets
no storage default and no narrowing to a definition interface. `getDefinition()` throws instead of
returning null unless its second argument is `FALSE`, so the null goes away even for an entity type
id the index cannot resolve; only a computed second argument keeps the declared type. The
revisionable storage methods (`loadRevision`, `loadRevisionUnchanged`, `loadMultipleRevisions`,
`createRevision`) are covered as well. `EntityRepositoryInterface` lookups type by their entity
type id argument, and `getTranslationFromContext($entity)` returns the class it was handed.

Core 11.3 up to 12.0 keeps the `$cacheability` parameter of the list builder's `getOperations()`
and `getDefaultOperations()` commented out and reads it through `func_get_args()`, and its own list
builders pass it on with `parent::getOperations($entity, $cacheability)`. The plugin declares the
parameter for those calls, so they are not reported as `too-many-arguments`. A named
`cacheability:` argument fails at runtime on those versions, so that call keeps the declared
signature.

`getTranslationLanguages()` and the language manager's `getLanguages()` come back keyed by
language code as `string`, where core documents `LanguageInterface[]`. A language code matches
`LanguageInterface::VALID_LANGCODE_REGEX`, which starts with a letter, so PHP never turns one into
an integer key, and `array_keys($entity->getTranslationLanguages())` hands a language code to
`getTranslation()` without a `less-specific-argument`.

Entity queries carry tags too. `getQuery()`, `getAggregateQuery()`, `\Drupal::entityQuery()` and
`\Drupal::entityQueryAggregate()` return `QueryInterface<'node', 'content', 'unchecked', 'ids'>`, and
`accessCheck()` and `count()` update the tags through the fluent chain. `execute()` reads them. It
returns `int<0, max>` after `count()`, `array<int|string, string>` of ids for a content query,
`array<string, string>` for a config query and `list<array<string, mixed>>` for an aggregate. A
query whose entity type is not known, from `\Drupal::entityQuery($id)` or `getQuery()` on a bare
`EntityStorageInterface`, is tagged with an unknown entity type so the chain is still tracked.

An entity query executed without `accessCheck()` on its chain is reported as
`drupal/entity-query-access-check`, unless its entity type is a known config entity type.
`accessCheck(FALSE)` counts as a decision and is not reported. `$query->accessCheck(TRUE);` as a
statement of its own retags the variable through a `$this` assertion, so the fluent chain is not
required. A bare `ConfigEntityStorageInterface` receiver tags the query as config of unknown type,
which is exempt. The check reads the tags alone, so a query built in one method and executed in
another is only tracked when the tagged type flows through, and a query the provider never saw
being created is not reported.

An entity type id that no indexed entity type declares is reported as `drupal/unknown-entity-type`
on the entity type manager's handler getters and `getDefinition()`, once the `user` entity type is
in the index; `getDefinition($id, FALSE)` asks for null and is not reported, and test code is
skipped.

Not modelled, so the attribute's classes stand: `hook_entity_type_build()` and
`hook_entity_type_alter()` implementations that swap handlers or entity classes. A handler class
Mago has not scanned keeps the declared interface type.

## Magic entity fields

`ContentEntityBase::__get()` hands back a field item list for any field name, so an undeclared
property on a `FieldableEntityInterface` descendant reads as
`Drupal\Core\Field\FieldItemListInterface`. `FieldItemList::__get()` forwards to the first item,
and `FieldItemBase::__get()` reads the properties the field type defines, so an undeclared property
on a field item list or on a field item is accepted as `mixed`: the field type decides what comes
back, and typing it would be a guess.

```php
$node->field_thing;              // Drupal\Core\Field\FieldItemListInterface
$node->field_thing->value;       // mixed, no longer an undefined property
$item->value;                    // mixed on a FieldItemInterface as well
$node->field_thing = 'a string'; // allowed, the way __set() is
$node->original;                 // Drupal\Core\Entity\EntityInterface|null, reported as deprecated
```

Writing a field stays `mixed`, because `$node->field_thing = 'x'` is valid Drupal. `original` is
typed as the entity before the save rather than as a field, on config entities too. Drupal 11.2
deprecates the magic property in favor of `getOriginal()` and `setOriginal()`, and every read,
write, `isset()` and `unset()` of it is reported as `drupal/deprecated-original`.

A property PHP itself resolves keeps its own type: a declared property, an inherited one and a
`@property` tag all win over the field type. When the receiver's static type is an interface,
Mago still reports the access, since the interface declares no `__get()`; typing the variable as
the entity class silences it.

## Config

`\Drupal::config('name')`, `$configFactory->get('name')`, `getEditable('name')` and the
`$this->config('name')` of a class using `ConfigFormBaseTrait` return a config object tagged with
its name, for example `ImmutableConfig<'system.maintenance'>`. `->get('key')` on it is typed from
the config schema, read from `core/config/schema/` and from the `config/schema/` directory of every
module, profile and theme under the root, test extensions included.

```php
\Drupal::config('system.maintenance')->get('message');   // string|null
\Drupal::config('system.cron')->get('threshold.requirements_warning'); // int|null
\Drupal::config('user.role.anonymous')->get('permissions'); // array<int|string, string>|null
\Drupal::config('system.maintenance')->get('nope');      // reported as drupal/config-unknown-key
// array{'requirements_error': int, 'requirements_warning': int}|null
\Drupal::config('system.cron')->get('threshold');
```

Only configs whose schema carries the `FullyValidatable` constraint are typed and checked, since
only then do stored values have to match the schema. Anything else keeps the declared `mixed`. The
mapping follows `TypedConfigManager`: a definition's `type:` names a parent whose keys it
inherits, a config name without an exact definition falls back to wildcard names such as
`user.role.*`, and a `type:` with a `[%key]` style placeholder is left untyped. Schema types map
to `string` (`string`, `label`, `text`, `path`, `uri`, `email`, `uuid`, `langcode` and the other
string-like types), `int` (`integer`, `weight`, `timestamp`), `float`, `bool`, `array<int|string, T>`
for a sequence, and an array shape for a mapping. The shape lists every key of the mapping, required
unless it says `requiredKey: false`, with `null` added for `nullable: true`; Drupal rejects keys a
fully validatable mapping does not list, so the shape holds no others. A mapping that lists no keys
is `array<string, mixed>`. A key passed to `->get()` can always be absent, so `null` stays in the
union; `->get()` with no key is the whole object's shape.

`read('name')` on a config `StorageInterface` returns the stored data of the whole object, with the
same shape, or `false` when nothing is stored under the name:

```php
// array{'_core'?: array{'default_config_hash': string}, 'langcode'?: string,
//   'module': array<int|string, int>, 'profile'?: null|string, 'theme': array<int|string, int>}
//   |false
$storage->read('core.extension');
```

A storage for another collection, such as a language override, holds only part of the object, which
the shape does not show.

A literal name that no schema describes is reported as `drupal/config-unknown-name`, a warning: a
misspelt name hands back an empty config object without complaint. The check needs the module the
name starts with to be in the codebase, so reading an optional module's config is left alone.

```php
\Drupal::config('system.maintenence');  // reported as drupal/config-unknown-name
```

## Plugins

`$manager->createInstance('id')` returns the plugin class for core's attribute-based managers:
blocks, actions, conditions, field types, widgets, formatters, layouts, mail, queue workers,
render elements, typed data, filters, image effects, media sources, REST resources and the other
managers whose constructor names an attribute class. A contrib manager that extends one of them,
or a receiver typed by the manager's interface, is recognised through the class ancestry. Plugin
classes are read from Mago's class metadata: every instantiable class descending from
`PluginInspectionInterface` that carries one of those attributes or a subclass of one, so vendor
code counts and field items reach it through `TypedData`. Legacy docblock annotations such as
`@Block(id = "…")` or `@RenderElement("…")` are read from the analyzed `src/Plugin/**/*.php` and
`src/Element/*.php` files and mapped to the attribute of the same short name, with `@SearchPlugin`
and `@FormElement` mapped by hand. An annotation on an abstract class, in a `@code` sample, or on a
class that also carries the attribute does not count, and two annotated classes claiming one id
cancel each other out like attributed ones do.

```php
$blockManager->createInstance('page_title_block');    // Drupal\Core\Block\Plugin\Block\PageTitleBlock
$blockManager->createInstance('system_menu_block:main'); // Drupal\system\Plugin\Block\SystemMenuBlock
$blockManager->createInstance('no_such_block');       // Broken, and reported as drupal/unknown-plugin
```

A `base:derivative` id resolves through its base plugin, the longest one declared, since a base can
hold a colon itself, as core's `entity:save_action` does. That is wrong for a deriver that sets a
`class` per derivative. Two classes declaring the same id cancel each other out. On a fallback
manager (blocks, entity reference selection, filters) an unknown id is typed as the fallback
plugin, since that is what runs. A plain id that no scanned plugin declares is reported as
`drupal/unknown-plugin`, but only once a core plugin of that kind is in the index, so a workspace
that leaves core out of the analyzed code stays quiet, and never in test code, where managers are
mocked. Not modelled: `hook_*_info_alter()` changes to definitions, managers outside the table, and
YAML-discovered plugins such as menu links.

## Deprecation scopes

Mago reports every call to a deprecated symbol. Drupal has places where that call is the point of
the code or is allowed, and the plugin drops the report there. Four markers open a scope:

- a `@group legacy` docblock on a class or a method,
- a `@deprecated` docblock on a class-like or a function, since deprecated code may use other
  deprecated code (phpstan-deprecation-rules skips the same places),
- a PHPUnit `#[IgnoreDeprecations]` attribute on a class or a method,
- the arguments of `DeprecationHelper::backwardsCompatibleCall()`, whose deprecated branch is what
  runs on older core.

A class-level marker covers the whole class, a method-level one covers that method's body and
stops at its closing brace. A `@deprecated` property or constant opens no scope. The codes filtered
are `deprecated-class`, `deprecated-closure`, `deprecated-constant`, `deprecated-function`,
`deprecated-method` and `deprecated-trait`. `deprecated-feature` is left alone, since it is about
PHP language features rather than the Drupal API a legacy test exercises. The plugin's own
`deprecated-original` skips the same scopes.

The scopes are read from the file's own bytes with PHP's tokenizer. A file that holds none of the
four markers is screened out by a substring test, and Mago batches a file's issues into one
request, so a marked file is tokenized once.

A method that overrides a deprecated method is a scope too, whatever its own docblock says, unless
it says `@not-deprecated`. PHPStan counts such a method as deprecated, and a decorator forwarding
the deprecated method it implements is the usual case:

```php
/**
 * {@inheritdoc}
 */
public function invalidateAll() {
  // Not reported: CacheBackendInterface::invalidateAll() is deprecated.
  $this->inner->invalidateAll();
}
```

This takes the codebase, so it runs only for an issue no marker covers. The tokens name the method
and its class-like, and the class's ancestors say whether one of them declares the method
deprecated. Mago analyzes a trait once, on its own, so a trait method counts when every class that
gets it from the trait inherits a deprecated declaration; finding those classes is a search by
method name across the codebase, and only a trait pays for one.

## Form responses

`FormBuilder::retrieveForm()` accepts a `Response` from a form's `buildForm()`: it throws an
`EnforcedResponseException`, and Drupal sends that response instead of the page. Confirmation forms
use this to redirect when there is nothing left to confirm. `FormInterface::buildForm()` documents
an array, so Mago reports the return as `invalid-return-statement`. The plugin drops that report
when the method belongs to a `FormInterface` class and every type it returns there is a `Response`.

```php
public function buildForm(array $form, FormStateInterface $form_state) {
  if ($this->selection === []) {
    // Not reported.
    return new RedirectResponse($this->getCancelUrl()->toString());
  }
  // ...
}
```

Mago reports it under a native `RedirectResponse|array` too, since the inherited docblock narrows
the native type, and that report is dropped as well. A native return type that does not allow the
response, such as `: array`, keeps it: PHP throws a `TypeError` on that return before Drupal sees
the response. Any other returned value stays reported, and so does a `buildForm()` on a class that
is not a form. The method and the returned types are read from the wording of Mago's report, so a
reworded report in a later Mago release is shown again rather than hidden.

## Calls from traits

Mago analyzes a trait once, on its own, so a call on `$this` to a method the trait does not declare
is a missing method and returns `mixed`, even when every class using the trait has the method.
PHPStan checks a trait's body in each class that uses it instead. The plugin finds the classes
using the trait and, when every one of them has the method, types the call from their
declarations:

```php
trait RedirectTrait {
  protected function redirectUrl(): Url {
    // Typed from the getEntity() of the forms that use the trait.
    return $this->getEntity()->toUrl();
  }
}
```

The return type is the union of the classes' return types. `static` and `$this` mean the class
using the trait, so they stay the trait's `$this`, and a call chained on the result is typed the
same way. A parameter is typed only when every class gives it the same type, so an argument is
checked where the classes agree. A class that lacks the method keeps the report, and so does a trait
no class uses or one that declares no method of its own, since the classes are found through one of
its methods. A generic method returns `mixed`.

The search runs once per trait, and only the classes that use the trait without inheriting it from
another class using it are looked at. A subclass has every method and property its parent has, and
PHP keeps an override's return type within the one it overrides, so those classes answer for their
subclasses too. A test trait used by thousands of test classes through a few base classes costs a
few method lookups.

A property on a trait's `$this`, such as `$this->container`, gets the report half of this: when
every class using the trait has the property, declared or through a `@property` tag, the missing
property report is dropped. The value stays `mixed`. The SDK types a property only as a magic one,
and on a trait without `__get()` Mago would report that in turn. Mago records no reference for the
read either, so a private property, or a protected one in a final class, that only the trait reads
is still reported as `unused-property`.

## Fluent setters

Core documents `@return $this` on the interface and keeps the body in a trait with a bare
`{@inheritdoc}`. A trait has no parent to inherit from, so on a concrete class the call reads as
`mixed` and takes the rest of the chain with it. The plugin hands back the receiver for the 42
methods of 16 core traits whose bodies only ever `return $this`.

```php
AccessResult::allowed()->addCacheableDependency($entity)->andIf($other);  // AccessResultAllowed
$node->setPublished()->setOwnerId(1);                                     // Node
$form_state->setValue('a', 1)->setValue('b', 2);                          // FormState
```

A typed chain can surface calls Mago could not check before: `setReason()` lives on the neutral and
forbidden results rather than on `AccessResult`, and core reaches for it after an `isAllowed()`
check that says nothing about the type.

## Test assertions

Mago reads `@phpstan-assert`, and PHPUnit carries it on `assertNotNull()` and `assertInstanceOf()`,
so those narrow on their own. `assertNotEmpty()` and `assertEmpty()` carry nothing, and they are
the two Drupal tests reach for, which leaves a loaded entity nullable for the rest of the test and
reports every call on it. The plugin supplies the two facts PHPUnit leaves out.

```php
$node = Node::load(1);
$this->assertNotEmpty($node);
$node->label();            // narrowed, no possible-method-access-on-null
```

## Mock unions

Drupal's older tests document a mock as `@var X|MockObject`, from before PHP had intersection types.
Mago checks each half of a union on its own, so `expects()` is missing on `X`, and the call comes
back as `mixed`, which loses the type of the rest of the chain. phpstan-phpunit reads the union as
`X&MockObject`. Mago has no hook for that, and a provider type on the property would count as a
magic property, so the plugin fills in the two methods instead. It answers only when the type does
not declare them, and it sees that one type, not the union: `expects()` with one argument, checked
against `InvocationOrder`, and `method()` with a literal naming a method of the type, both return
`InvocationMocker`. PHPUnit's own types are left out, so `expects()` on the `Stub` half of an
`X|Stub` union or on a `MockBuilder` is still reported.

```php
/** @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject */
protected $moduleHandler;

$this->moduleHandler->expects($this->once())->method('invokeAll')->willReturn([]);  // no issues
```

Since the provider never sees the union, it also answers when the type is `X` alone. A separate
check sees the whole receiver type and reports those calls as `phpunit/mock-call-on-plain-type`, a
warning: the value is a mock at runtime, but the property, parameter or return type it comes from
leaves `MockObject` out. PHPStan reports the same calls as undefined methods.

```php
/** @var \Drupal\Core\Extension\ModuleHandlerInterface */
protected $moduleHandler;

$this->moduleHandler->expects($this->once());  // phpunit/mock-call-on-plain-type
```

The other direction stays: calling `invokeAll()` on the union still reports it missing on
`MockObject`, once per call and with the type of the call intact, since the plugin never sees `X`
from that side. A typed chain can also surface calls Mago could not check before, such as `with()`
after `method()` on a stub, which PHPUnit's `InvocationStubber` does not have.

## Prophecy calls

`$prophecy->getFoo()` on an `ObjectProphecy<Foo>` goes through `__call()`, which returns a
`MethodProphecy`, and Mago reports every such call as undocumented, since the class has no
`@method` tag for it. The plugin types the call as a `MethodProphecy` when the prophesized class
declares the method, or when the class is not known. A method the class lacks stays reported, as
Prophecy throws for it.

```php
$storage = $this->prophesize(EntityStorageInterface::class);
$storage->load(1)->willReturn($node);  // MethodProphecy, no non-documented-method
$storage->notAMethod();                // still reported
```

A prophecy documented as `@var Foo|ProphecyInterface` is left alone. A prophecy is never a `Foo`,
so the `Foo` half types the call as the real method's result, and the docblock is what needs
fixing, to `ObjectProphecy<Foo>`. Every call on such a value is reported on one half or the other,
so the plugin also reports the docblock itself, once, as `phpunit/prophecy-union`, a warning. It
covers properties, parameters and return types that union `ProphecyInterface` or `ObjectProphecy`
with another class.

```php
/** @var \Drupal\Core\Extension\ModuleHandlerInterface|\Prophecy\Prophecy\ProphecyInterface */
protected $moduleHandler;  // phpunit/prophecy-union: document it as ObjectProphecy<ModuleHandlerInterface>
```

`willImplement()` and `willExtend()` declare `@phpstan-this-out static<T&U>`, which Mago does not
apply ([mago#2395](https://github.com/carthage-software/mago/issues/2395)), so the plugin adds the
type they name to the prophecy: through the return type for a chain, and by narrowing the variable
for a call on one.

```php
$block = $this->prophesize(BlockPluginInterface::class);
$block->willImplement(PreviewFallbackInterface::class);
$block->getPreviewFallbackString()->willReturn('Placeholder');  // found on the added interface
```

Prophecy makes a double of an interface that is `Traversable`, but neither an `Iterator` nor an
`IteratorAggregate`, an `Iterator`. The plugin accepts `current()`, `key()`, `next()`, `rewind()` and
`valid()` on such a prophecy.

```php
$items = $this->prophesize(FieldItemListInterface::class);
$items->valid()->willReturn(TRUE, FALSE);  // added by Prophecy, no non-documented-method
```

## PHPStan ignores

A project that runs PHPStan next to Mago keeps `@phpstan-ignore` comments for the errors it
accepts, and Mago often reports the same lines. The `phpstan-ignores` plugin reads those comments
and drops the Mago issues that report the same finding, so the line needs no `@mago-expect` on top.
It is off by default; turn it on in `mago.toml`:

```toml
[analyzer]
plugins = ["phpstan-ignores"]
```

```php
// @phpstan-ignore return.type (the caller handles NULL)
return NULL;  // no invalid-return-statement
```

The comment forms are PHPStan's: `@phpstan-ignore` with comma-separated identifiers and an optional
reason in parentheses, either at the end of the line or above it, where it covers the next line of
code; `@phpstan-ignore-next-line`; and `@phpstan-ignore-line`. An identifier drops only the Mago
codes the plugin lists for it, so `@phpstan-ignore return.type` still lets a missing method on the
same line through. The two line forms drop every listed code, and an identifier the plugin does not
list drops nothing. The list covers PHPStan's identifiers for argument, return and property types,
argument counts, undefined symbols, deprecations, always-true and always-false conditions, missing
types and offset access, and the deprecation identifiers of phpstan-deprecation-rules; it lives in
`PHPStanIgnoreFilter`. phpstan-drupal's identifiers are not in it. The `drupal/` and `phpunit/`
checks report after Mago runs the plugin, so a comment never drops one of them.

An issue counts as being on the covered line when it starts there. Mago matches `@mago-expect`
pragmas before the plugin runs, so a pragma next to a `@phpstan-ignore` for the same issue still
counts as used: it can be removed, but Mago does not point it out.

## Stub files

Drupal documents a few core signatures more loosely than the code behaves. The worker loads the
stub files under `resources/stubs/` into Mago's symbol table before the analysis starts. They are
read for symbols only: never linted, formatted or reported on.

```php
$url->toString();      // string
$url->toString(TRUE);  // Drupal\Core\GeneratedUrl
$url->toString($flag); // Drupal\Core\GeneratedUrl|string
$cache->get('cid');    // object{cid: string, data: mixed, created: int|float|numeric-string, expire: int|numeric-string, tags: list<string>, valid: bool, ...}|false
```

A stub only wins where core is a dependency. When core itself is the analyzed code, its own
declaration is the one Mago keeps, so `--core` runs see no change.

A stub replaces the class header: `extends`, `implements` and class constants come from the stub,
and the descendants of a stubbed interface are built from it, so a stub has to restate the whole
header and every method whose absence a subclass would notice. A restated method's docblock
replaces core's too, `@deprecated` included, so the stubs repeat core's parameter types and
deprecations. Two members change in the two files shipped, the rest are there to keep the type
intact.

Not translated from phpstan-drupal's stub directory:

- The generics plumbing of the `TypedData` and `FieldItemList` hierarchies (about 60 files, most of
  them empty class bodies that only fix PHPStan's inheritance ordering).
- The `@property` docblocks of the field item classes. Each pins a parent class core moves between
  releases, `EntityReferenceItem` already extends `EntityReferenceItemBase` in 11.4, and they buy a
  few dozen warnings on concretely typed items.
- `AccessResult::allowedIf()` and `forbiddenIf()` conditional returns. A non-literal condition gives
  the union, and `AccessResultAllowed` does not implement `AccessResultReasonInterface`, so
  `allowedIf($x)->setReason()` becomes an error: 118 new issues on the contrib set.
- `$modules` on `KernelTestBase` and `BrowserTestBase`. Restating those class headers drops the
  PHPUnit ancestry the test rules and every assertion ride on.
- Whatever the plugin's own providers already do: entity storage, entity queries, container
  lookups, config, plugin managers, `getDefinition()`.

## Checks

Beyond the types, the plugin ports phpstan-drupal's Drupal-specific rules as analyzer checks. Every
code below is reported as `drupal/<code>`.

Test code, any file under a `tests` directory, is left alone by the lookup checks
(`deprecated-service`, `unknown-service`, `unknown-entity-type`, `unknown-plugin`, `load-include`,
`config-unknown-name`),
by `deprecated-original`, by `global-drupal-call` and by the two plugin manager checks: tests mock
managers, build their own containers and exercise deprecated code on purpose. The lookup checks and
`deprecated-original` also skip hook documentation in `*.api.php` files, whose examples use made-up
ids.

| Code | Level | What it reports |
| --- | --- | --- |
| `deprecated-original` | Warning | A read, write, `isset()` or `unset()` of the magic `original` property on an entity, deprecated in Drupal 11.2 in favor of `getOriginal()` and `setOriginal()`. An entity class that declares `$original` itself is left alone, and so are the deprecation scopes below. |
| `deprecated-service` | Warning | `get()`, `\Drupal::service()` or the class resolver asked for a service whose definition says `deprecated:`. |
| `unknown-service` | Warning | `get()` or `\Drupal::service()` asked for a string id no services file or provider defines, or for a private service the compiled container leaves out. |
| `unknown-entity-type` | Warning | An entity type manager getter asked for an id no entity type declares. |
| `config-unknown-key` | Error | `$config->get('key')` for a key a fully validatable schema does not list. Update code is skipped: an `.install` file and a `.post_update.php` read the keys an older version of the module wrote. |
| `config-unknown-name` | Warning | `\Drupal::config()`, a config factory's `get()` or `getEditable()`, or a config form's `config()` asked for a literal name no schema describes, wildcards included. Only a name whose module is in the codebase is checked, since a module reading an optional module's config cannot expect its schema. Update code is skipped, like for `config-unknown-key`. |
| `unknown-plugin` | Warning | `createInstance('id')` on a core manager when no scanned plugin declares the id. |
| `entity-query-access-check` | Error | `execute()` on an entity query chain without `accessCheck()`, unless the entity type is a known config entity type. |
| `entity-storage-injection` | Warning | A constructor parameter typed as an entity storage. Inject the entity type manager instead. An entity handler is handed its own storage by the entity type manager, so its `$storage` parameter, or `$storage_controller` in views data, is left alone; any other storage it takes is reported. |
| `entity-storage-property` | Warning | A property whose declared or `@var` type is an entity storage, other than an entity handler's own `$storage`. A trait's property counts too, and the trait's `$storage` is left alone when every class using the trait is a handler. |
| `global-drupal-call` | Warning | `\Drupal::…` (or a call on a subclass or instance of `Drupal`) inside an instance method of a class implementing `ContainerInjectionInterface` or `ContainerFactoryPluginInterface`. Static methods and plain services are not checked, and neither is a constructor with a parameter that accepts null: Drupal's deprecation policy adds a new service that way, with a `\Drupal::service()` fallback for callers that do not pass it yet. |
| `dependency-serialization-property` | Error | A private property, or, before PHP 8.4, a readonly non-scalar property declared below the class composing the trait, in a class that composes `DependencySerializationTrait` itself or descends from one of core's bases that do (forms, plugins, entity handlers; not controllers, plugin forms or views plugins). Promoted constructor parameters count, static properties do not. |
| `logger-from-factory` | Error | A logger channel fetched from the factory in the constructor of a class using `DependencySerializationTrait`. |
| `deprecated-hook` | Warning | A `#[Hook]` method or a procedural `<module>_<hook>()` implementing a hook whose `hook_*()` is `@deprecated`. |
| `hook-form-alter-signature` | Error | A form alter hook method whose `$form` is not taken by reference or typed as something other than an array, whose second parameter is typed as something other than `FormStateInterface`, whose third is typed as something other than a string, or which requires a fourth argument. Untyped parameters are only checked for the reference. Taking fewer than three parameters is fine, since PHP drops the extra arguments and core does it in fourteen places. |
| `hook-entity-operation-cacheability` | Error | `hook_entity_operation` or its alter without the `CacheableMetadata` parameter, once core's api.php declares it. |
| `test-class-suffix` | Error | A concrete `TestCase` descendant whose name does not end in `Test`. |
| `internal-class-extension` | Warning | A class extending an `@internal` class owned by another module; a module's tests count as the module. An anonymous class counts too, owned by the module of the class it is written in. |
| `test-modules-visibility` | Error | A public `$modules` on a test class. |
| `browser-test-default-theme` | Error | A concrete `BrowserTestBase` descendant whose name ends in `Test`, on a themeless profile, with no `$defaultTheme` set on it or on a base class. Update path tests, which install from a database dump, are left alone, and so are tests on a `NULL` or `FALSE` profile, which install from existing configuration and take its theme. |
| `list-builder-cacheability` | Error | `getOperations()` or `getDefaultOperations()` without the `CacheableMetadata` parameter, on core 11.3 up to 12.0, which keep the parameter commented out in the interface and read it through `func_get_args()`. Nothing is reported when the core version cannot be read from `core/lib/Drupal.php`. Off in `--core` mode. |
| `plugin-manager-alter-info` | Warning | A `DefaultPluginManager` subclass in the analyzed code whose constructor, `parent::__construct()` included, never calls `alterInfo()`. |
| `plugin-manager-cache-backend` | Warning | A `DefaultPluginManager` subclass in the analyzed code whose constructor, `parent::__construct()` included, never calls `setCacheBackend()`. A call without the cache key is Mago's own `too-few-arguments`, since core requires it. |
| `config-entity-export` | Error | A config entity type in the entity type index, from a `#[ConfigEntityType]` attribute or `@ConfigEntityType` annotation, without `config_export`. |
| `plugin-annotation-context` | Error | A plugin annotation declaring its contexts under `context`, which Drupal 9 renamed to `context_definitions`. |
| `load-include` | Error | `loadInclude()` naming a file that does not exist in the module directory; Warning when the module is unknown. |
| `cacheable-dependency` | Warning | `addCacheableDependency()` handed a value that cannot implement `CacheableDependencyInterface`, which drops the thing it was added to to max-age 0. Reported for a scalar, an array, null and a final class that does not implement it; `mixed`, a bare `object`, an interface, an unscanned class and any class that can be extended stay quiet, since the value passed may be a subclass that implements it, as core's `CacheableHttpException` does. |

Three more ports are linter rules, since they need no types: `drupal/discouraged-function`,
`drupal/symfony-yaml-parse` and `drupal/render-callback`; see [rules.md](rules.md).

The checks read Mago's class metadata, not the source. A class-level hook asks the host for the
class node's span and the file text; the names resolved inside that span say whether any check can
apply (a `#[Hook]` attribute, an entity storage type, `DependencySerializationTrait`, a config
entity type, an annotated plugin), and only then is the class looked up in the codebase, with its
methods and properties fetched on demand. A storage type that only a `@var` tag names is not a
resolved name, so the class text is searched for such a tag as well. Ancestry comes from the host's
class-like targets (descendants of `TestCase`, `BrowserTestBase`, `EntityListBuilderInterface` and
the core bases that compose `DependencySerializationTrait`). The `\Drupal::` and `getStorage()`
calls and the logger factory `get()` calls come through method-call hooks and are placed in their
class and method by location. Plugin managers are audited once after analysis from the symbol
reference graph, which says what their constructors call. Argument types arrive in source order,
which is the parameter order only while a call stays positional, so the checks that read a string
out of a multi-string signature (the entity type manager getters, `loadInclude()`, the renderer's
`addCacheableDependency()`) ask for the call's syntax as well and match `getHandler(handler_type:
'access', entity_type_id: 'node')` to the right parameter. The others read a position directly,
because a call naming their parameters out of order puts an int or an array where they expect a
literal string and they stop there. The internal-class check reads the `@internal` classes off the
PHP files under `core/lib` and every module's `src` directory before the analysis starts, so the
host can send only their descendants, and confirms the flag from metadata. Not ported: the
`AccessResult::allowedIf()` condition check (Mago's own analysis reports the always-true
comparison), and the `module_load_include()` check, whose function is gone in Drupal 11.

## Finding the Drupal root

Mago starts workers in the directory of the effective `mago.toml`. From there the worker uses, in
order: the `--root=PATH` worker argument, `extra.drupal-scaffold.locations.web-root` from
`composer.json`, then the directory itself, `web/`, `docroot/`, `html/` and `public/`, taking the
first that holds `core/lib/Drupal.php`. A workspace without core falls back to the directory
itself and still indexes any paired services files under it.

```toml
[extension-hosts.drupal]
command = ["php", "vendor/amateescu/mago-drupal/resources/worker.php", "--root=docroot"]
```

## Cost

Mago runs the worker as a pool of processes, one request at a time per process, and every process
needs the indexes. The disk-backed ones (the directory walk, services YAML, config schema, hook
documentation, the `@internal` class list) are parsed once and kept in a cache directory, keyed by
the modification times and sizes of the files they came from, so an edited file misses the cache and
nothing goes stale. Files touched in the last two seconds may still be changing, so they are parsed
without the cache. Entry names also carry a hash of the extension's own code, so an update never
reads what an older version wrote. The metadata-backed ones (entity types, plugins) cannot outlive a
run, so within a run the first worker to need one builds it and the others load its result; their
entries are keyed by the host process, its start time and Mago's generation for the frozen codebase,
and a new generation's entry replaces the older ones.

A watch or editor session analyzes again in the same workers. Every index is dropped when a request
arrives for a new generation, since Mago reruns the scan hooks of an incremental analysis only when
a file they target changed, and an edited services file or entity class reaches none of them.

The cache lives under the system temporary directory in `mago-drupal-<uid>/`, and is only used while
it belongs to that user and nobody else can write to it. Set `MAGO_DRUPAL_CACHE=/some/dir` to move it
or `MAGO_DRUPAL_CACHE=0` to switch it off. With a warm cache a worker's first request costs a few
milliseconds of loading plus one metadata build per run; cold, it parses core's 180 services files
and 180 schema files itself, about half a second.

The `@internal` class list is the one index a worker needs before it answers anything, because the
host takes the class names to watch from the hook's targets when the worker starts. Both the walk
of the source directories and the class scan are cached: the file listing is reused while every
directory it read keeps its modification time, and the parsed classes while every file keeps its
modification time and size. That leaves 39 to 46 ms of startup per worker on a site with 750
modules, most of it stat calls, and `--core` skips the check and its scan entirely. A lint run pays
it too, since a worker registers its analyzer plugins whatever it is asked to do.

The class-level checks receive every class of the analyzed code as one node span, with no subtree;
the resolved names in that span, and a search of its text for a `@var` tag naming a storage, decide
which classes are looked up at all, and each lookup is a few metadata requests, batched by class.
The file text is already shipped for the other checks that ask for it, so the lifecycle requests on
core stay at 64.9 MB either way.

`deprecated-original` is the one check that sees every property access, because Mago targets node
kinds and not property names. It asks for nothing but the file text, which other checks already
ship, and a byte compare on the end of each access sends all but `->original` straight back; only
those ask the host for the receiver's type. On core it adds 2.5 MB to the 62 MB of lifecycle
requests and 0.9 s of worker CPU spread over the pool, which does not show in the wall time.

On core (11,000 files) the extension adds
about a second to a run that takes two and a half without it; on a contrib module it adds a few
tenths of a second once the cache is warm.
