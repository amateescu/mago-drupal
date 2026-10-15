# Analyzer

PHP alone doesn't tell Mago what a Drupal service, entity storage, config object or plugin is. The
worker adds three plugins to `mago analyze` that give the analyzer those types, and add checks for
mistakes that are specific to Drupal and to PHPUnit.

- `drupal`, on by default: the types of services, entities, config and plugins, and the checks that
  port phpstan-drupal's Drupal-specific rules and the DrupalPractice sniffs that need types.
- `phpunit`, on by default: the types of PHPUnit assertions, mocks and Prophecy doubles, and the
  checks for docblocks that type a double wrong. It depends on nothing specific to Drupal. See
  [Tests](tests.md).
- `phpstan-ignores`, off by default: lets `@phpstan-ignore` comments drop the Mago issues that
  report the same finding. See [PHPStan ignores](phpstan-ignores.md).

With `disable-default-plugins = true`, list `drupal` and `phpunit` in `[analyzer] plugins` to keep
them.

## The topic pages

Each topic page starts with the types for its topic, then has an entry for each check. An entry
gives the check's level, where the check comes from, the phpstan-drupal rule or Coder sniff ported by
the check, what it reports and what it leaves alone, and a collapsed note that compares it with
phpstan-drupal or Coder. Every code starts with `drupal/` or `phpunit/`. The
[table of checks](#checks) below lists them all.

Two pages have types only:

- [Core types and stubs](core-types.md): the core methods that are typed the way the code behaves,
  and the stub files;
- [PHPStan ignores](phpstan-ignores.md): the `phpstan-ignores` plugin.

Most checks skip test code, which is any file under a `tests` directory. The checks that look up
services, entity types, plugins and config also skip hook documentation in `*.api.php` files. Each
entry lists what its check skips.

To turn a check off, use Mago's own `[analyzer] ignore`, see
[Turning rules off](../setup/worker-arguments.md#turning-rules-off). How the worker finds the Drupal
root is under [Worker arguments](../setup/worker-arguments.md#finding-the-drupal-root), and how it
caches what it reads under [Install](../setup/install.md#index-cache).

## phpstan-drupal rules

Five ports of phpstan-drupal rules are linter rules, not analyzer checks, since they need no types:
[`drupal/discouraged-function`](../rules/phpstan-drupal.md#drupaldiscouraged-function),
[`drupal/render-callback`](../rules/phpstan-drupal.md#drupalrender-callback),
[`drupal/symfony-yaml-parse`](../rules/phpstan-drupal.md#drupalsymfony-yaml-parse),
[`drupal/todo-current-issue`](../rules/phpstan-drupal.md#drupaltodo-current-issue) and
[`drupal/test-class-suffix`](../rules/naming-and-imports.md#drupaltest-class-suffix). The last one
reads the parent's name, and the analyzer check
[`drupal/test-class-name`](tests.md#drupaltest-class-name) reports the misnamed test classes that
it can't see that way. The [phpstan-drupal](../phpstan/phpstan-drupal.md) page maps every rule and
extension of phpstan-drupal, including the parts that aren't ported.

## Checks

Every check, by page.

<!-- docs-gen:checks -->

## Services

The analyzer types what the service container hands back, so a lookup such as
`\Drupal::service('entity_type.manager')` gets the class of its service. The checks report lookups
of unknown, private and deprecated services, and service definitions with the wrong number of
arguments.

| Check | Level | What it reports |
| --- | --- | --- |
| [`drupal/deprecated-service`](services.md#drupaldeprecated-service) | warning | A `get()`, `\Drupal::service()` or class resolver lookup of a service whose definition, or its `parent:`, has a `deprecated:` key. |
| [`drupal/unknown-service`](services.md#drupalunknown-service) | warning | A `get()` or `\Drupal::service()` lookup of a string id that no services file or provider defines. |
| [`drupal/service-argument-count`](services.md#drupalservice-argument-count) | error, or warning for extra arguments | A service definition with fewer `arguments:` than the constructor of its class requires. |

## Dependency injection

Checks for classes that call `\Drupal` where they can take a service through dependency injection,
for entity storages that a class takes or keeps instead of asking the entity type manager, and for
the properties that `DependencySerializationTrait` can't restore.

| Check | Level | What it reports |
| --- | --- | --- |
| [`drupal/global-drupal-call`](injection.md#drupalglobal-drupal-call) | warning | A call to a method of `\Drupal` in an instance method of a class that implements `ContainerInjectionInterface` or `ContainerFactoryPluginInterface`. |
| [`drupal/static-entity-load`](injection.md#drupalstatic-entity-load) | warning | A static `load()` or `loadMultiple()` call on an entity class, such as `Node::load()`, in an instance method of a class that can take services. |
| [`drupal/entity-storage-injection`](injection.md#drupalentity-storage-injection) | warning | A constructor parameter typed as an entity storage. |
| [`drupal/entity-storage-property`](injection.md#drupalentity-storage-property) | warning | A property whose declared type, or `@var` type, is an entity storage. |
| [`drupal/logger-from-factory`](injection.md#drupallogger-from-factory) | error | A `get()` call on a `LoggerChannelFactoryInterface` whose result is assigned straight to a property of `$this`, in the constructor of a class that uses `DependencySerializationTrait`. |
| [`drupal/dependency-serialization-property`](injection.md#drupaldependency-serialization-property) | error | A private property of a class that uses `DependencySerializationTrait`, the composing class included. |

## Entities

The analyzer reads the entity types of the codebase, and types what the entity type manager, entity
handlers, entity queries and entity fields hand back. The checks report unknown entity type ids,
entity queries without an access check, uses of the deprecated `original` property, and entity type
definitions and list builders that leave out what core asks for.

| Check | Level | What it reports |
| --- | --- | --- |
| [`drupal/unknown-entity-type`](entities.md#drupalunknown-entity-type) | warning | A literal entity type id that no indexed entity type declares, passed to a getter of the entity type manager. |
| [`drupal/entity-query-access-check`](entities.md#drupalentity-query-access-check) | error | `execute()` on an entity query whose chain doesn't have an `accessCheck()` call, unless the entity type is a known config entity type. |
| [`drupal/deprecated-original`](entities.md#drupaldeprecated-original) | warning | A read, a write, an `isset()` and an `unset()` of `original` on an entity. |
| [`drupal/config-entity-export`](entities.md#drupalconfig-entity-export) | error | A config entity type in the entity type index without `config_export`. |
| [`drupal/list-builder-cacheability`](entities.md#drupallist-builder-cacheability) | error | A list builder's `getOperations()` or `getDefaultOperations()` method without a second parameter typed as `CacheableMetadata`, on core 11.3 up to 12.0. |

## Config

The analyzer types config objects, and the values read from them by their config schema. The checks
report config names without a schema, and keys that a fully validatable schema doesn't list.

| Check | Level | What it reports |
| --- | --- | --- |
| [`drupal/config-unknown-key`](config.md#drupalconfig-unknown-key) | error | A `get('key')` call on a config object with a known name, for a key that the fully validatable schema of that config doesn't list. |
| [`drupal/config-unknown-name`](config.md#drupalconfig-unknown-name) | warning | A literal config name without a schema. |

## Plugins

The analyzer types the plugins created by core's plugin managers and the definitions read by
plugins. The checks report unknown plugin ids, plugin managers that never call `alterInfo()` or
`setCacheBackend()`, and plugin annotations with the `context` key removed in Drupal 9.

| Check | Level | What it reports |
| --- | --- | --- |
| [`drupal/unknown-plugin`](plugins.md#drupalunknown-plugin) | warning | A `createInstance()` call with a literal plain id that no scanned plugin declares. |
| [`drupal/plugin-manager-alter-info`](plugins.md#drupalplugin-manager-alter-info) | warning | A `DefaultPluginManager` subclass in the analyzed code whose own constructor never calls `alterInfo()`. |
| [`drupal/plugin-manager-cache-backend`](plugins.md#drupalplugin-manager-cache-backend) | warning | A `DefaultPluginManager` subclass in the analyzed code whose own constructor never calls `setCacheBackend()`. |
| [`drupal/plugin-annotation-context`](plugins.md#drupalplugin-annotation-context) | error | A plugin annotation with a `context` key. |

## Render arrays and forms

The analyzer accepts a response from a form's `buildForm()`, and checks the arguments that
`getForm()` passes on to it. The checks report callbacks in render and form arrays that can't be
called, trusted or serialized by core, and cacheable dependencies that can't have cacheability.

| Check | Level | What it reports |
| --- | --- | --- |
| [`drupal/unknown-callback`](render-and-forms.md#drupalunknown-callback) | error, or warning on `#value_callback` | A callback that names a method its class doesn't have. |
| [`drupal/non-static-callback`](render-and-forms.md#drupalnon-static-callback) | error, or warning on `#value_callback` | An array written as a static call that names an instance method. |
| [`drupal/non-public-callback`](render-and-forms.md#drupalnon-public-callback) | error, or warning on `#value_callback` | A callback that names a protected or private method, a `'::method'` of a form included. |
| [`drupal/untrusted-callback`](render-and-forms.md#drupaluntrusted-callback) | error | A callback under a render, date or component key that names a class method not trusted by core. |
| [`drupal/form-closure-callback`](render-and-forms.md#drupalform-closure-callback) | error | A closure, an arrow function or a first-class callable under a render or date key, written in a class that implements `FormInterface`. |
| [`drupal/invalid-callback`](render-and-forms.md#drupalinvalid-callback) | error | A value under a render, date or component key whose type PHP can never call: an int, a float, a bool, NULL, an array that can't be a two-element pair, or an object without `__invoke()`. |
| [`drupal/trusted-callback-override`](render-and-forms.md#drupaltrusted-callback-override) | warning | A method that overrides one with `#[TrustedCallback]`, declared in a parent class or in a trait that a parent uses, when the override doesn't repeat the attribute. |
| [`drupal/cacheable-dependency`](render-and-forms.md#drupalcacheable-dependency) | warning | An `addCacheableDependency()` call whose dependency is a scalar, an array, null, or a final class that doesn't implement `CacheableDependencyInterface`. |

## Translation

Checks for text that users see but that can't be translated, such as a literal form error or a
status message.

| Check | Level | What it reports |
| --- | --- | --- |
| [`drupal/untranslated-message`](translation.md#drupaluntranslated-message) | warning | A message that starts with a string literal, passed to `setErrorByName()` or `setError()` of `FormStateInterface`, or to `addMessage()`, `addStatus()`, `addWarning()` or `addError()` of `MessengerInterface`. |

## Hooks and modules

Checks for hook implementations, for the files that a module loads with `loadInclude()`, and for
classes that extend the internal classes of another module.

| Check | Level | What it reports |
| --- | --- | --- |
| [`drupal/deprecated-hook`](hooks.md#drupaldeprecated-hook) | warning | An implementation of a hook whose `hook_*()` documentation function has a `@deprecated` tag. |
| [`drupal/hook-form-alter-signature`](hooks.md#drupalhook-form-alter-signature) | error | A form alter implementation whose first parameter isn't taken by reference. |
| [`drupal/hook-entity-operation-cacheability`](hooks.md#drupalhook-entity-operation-cacheability) | error | An implementation of `hook_entity_operation()` whose second parameter isn't typed as `CacheableMetadata`. |
| [`drupal/load-include`](hooks.md#drupalload-include) | error, or warning when the module is unknown | A `loadInclude()` call that names a file that doesn't exist in the module's directory. |
| [`drupal/internal-class-extension`](hooks.md#drupalinternal-class-extension) | warning | A class that extends an `@internal` class owned by another module. |

## Deprecations

Where the analyzer leaves Drupal's deprecations alone, how to report only what is removed in the
next Drupal major, and the checks for the deprecations not reported by Mago.

| Check | Level | What it reports |
| --- | --- | --- |
| [`drupal/deprecated-class`](deprecations.md#drupaldeprecated-class) | warning | A class or an enum that implements a deprecated interface. |
| [`drupal/deprecated-method`](deprecations.md#drupaldeprecated-method) | warning | A call to a method that implements a deprecated interface method without saying so, an implementation from a trait included. |
| [`drupal/implicit-transaction-commit`](deprecations.md#drupalimplicit-transaction-commit) | warning | A `startTransaction()` call whose result is thrown away, which commits at once. |

## Traits

Mago analyzes a trait on its own, without the classes that use it. The analyzer types the calls
from a trait to the methods of those classes, and the fluent methods kept in traits by core. The
check reports a `self::` or `static::` call from a static method of a trait to an instance method.

| Check | Level | What it reports |
| --- | --- | --- |
| [`drupal/trait-non-static-call`](traits.md#drupaltrait-non-static-call) | error | A `self::name()` or `static::name()` call to an instance method, in a static method of a trait. |

## Tests

The analyzer types PHPUnit assertions, mocks and Prophecy doubles. The checks report assertions
that always pass, doubles that a type or a docblock gets wrong, and mistakes in Drupal's test
classes. The `phpunit` plugin does the PHPUnit and Prophecy work and depends on nothing in Drupal.
The `drupal` plugin has the checks for Drupal's test classes.

| Check | Level | What it reports |
| --- | --- | --- |
| [`phpunit/redundant-assertion`](tests.md#phpunitredundant-assertion) | warning | An assertion that the type of its value already guarantees, such as `assertTrue(TRUE)`, or `assertInstanceOf()` on a value already typed as that class. |
| [`phpunit/mock-call-on-plain-type`](tests.md#phpunitmock-call-on-plain-type) | warning | An `expects()` or `method()` call on a value whose type doesn't name a PHPUnit class or declare the method. |
| [`phpunit/prophecy-union`](tests.md#phpunitprophecy-union) | warning | A docblock type that unions `ProphecyInterface` or `ObjectProphecy` with another class. |
| [`drupal/test-modules-visibility`](tests.md#drupaltest-modules-visibility) | error | A public `$modules` property, static or not, typed or not, declared in a class that descends from PHPUnit's `TestCase`. |
| [`drupal/browser-test-default-theme`](tests.md#drupalbrowser-test-default-theme) | error | A browser test on a themeless profile that doesn't set `$defaultTheme`. |
| [`drupal/component-test-core-base`](tests.md#drupalcomponent-test-core-base) | error | A class in the `Drupal\Tests\Component` namespace that extends `UnitTestCase`, `KernelTestBase`, `BuildTestBase` or `BrowserTestBase`, directly or through its parents. |
| [`drupal/test-class-name`](tests.md#drupaltest-class-name) | error | A concrete class that descends from PHPUnit's `TestCase`, whose name doesn't end in `Test`, and that `drupal/test-class-suffix` doesn't take for a test. |

<!-- /docs-gen -->
