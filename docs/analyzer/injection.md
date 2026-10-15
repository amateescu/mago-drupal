# Dependency injection

Checks for classes that call `\Drupal` where they can take a service through dependency injection,
for entity storages that a class takes or keeps instead of asking the entity type manager, and for
the properties that `DependencySerializationTrait` can't restore.

---

## drupal/global-drupal-call

- **Level:** warning
- **Source:** phpstan-drupal
- **Ports:** phpstan-drupal's `GlobalDrupalDependencyInjectionRule` (partly)
- **Off with `--core`**

phpstan-drupal reports a `\Drupal::` call in a class that can take its services through
dependency injection. Such a class gets a service through its constructor, or through `create()`
for a form, a controller or a plugin.

**Reports:**

- a call to a method of `\Drupal` in an instance method of a class that implements
  `ContainerInjectionInterface` or `ContainerFactoryPluginInterface`. Examples are
  `\Drupal::service()` and `\Drupal::state()`;
- the same call in an instance method of a hook class, which is a class with a `#[Hook]` attribute
  on the class or on a method that it declares. Drupal registers a hook class as an autowired
  service;
- a call on a subclass or an instance of `Drupal` in those methods.

**Not reported:**

- a static method, which has no injected services to use;
- a constructor with a parameter that accepts null. Drupal's deprecation policy adds a new service
  to a constructor as a nullable parameter, and the constructor falls back to `\Drupal::service()`
  for callers that don't pass it yet;
- a call in a [deprecation scope](deprecations.md#deprecation-scopes), such as deprecated code and
  the callables passed to `DeprecationHelper::backwardsCompatibleCall()`. A deprecated method keeps
  its lookup until it is removed, and a callable for newer core can use a service that doesn't exist
  in older core;
- a call inside an anonymous class;
- any other class, such as a plain service;
- test code (any file under a `tests` directory).

??? example "Example"

    ```php title="modules/example/src/Hook/ExampleHooks.php"
    // Reported.
    namespace Drupal\example\Hook;

    use Drupal\Core\Hook\Attribute\Hook;

    class ExampleHooks {

      #[Hook('cron')]
      public function cron(): void {
        \Drupal::state()->set('example.last_cron', time());
      }

    }

    // Fine.
    namespace Drupal\example\Hook;

    use Drupal\Core\Hook\Attribute\Hook;
    use Drupal\Core\State\StateInterface;

    class ExampleHooks {

      public function __construct(
        protected StateInterface $state,
      ) {}

      #[Hook('cron')]
      public function cron(): void {
        $this->state->set('example.last_cron', time());
      }

    }
    ```

??? note "Compared with phpstan-drupal"

    - phpstan-drupal checks every class except tests, entities, typed data, stream wrappers,
      Nightwatch test setup classes, enums and traits. It also reports the services and other
      classes left alone by the check.
    - phpstan-drupal also reports the fallback in a constructor with a nullable parameter, a call in
      deprecated code or in a callable passed to `DeprecationHelper::backwardsCompatibleCall()`, and
      a call in an anonymous class.
    - phpstan-drupal checks a static call on `\Drupal` itself. The check also takes a call on a
      subclass of `Drupal`.
    - phpstan-drupal skips a class that implements PHPUnit's `Test`, and the check skips any file
      under a `tests` directory.

---

## drupal/entity-storage-injection

- **Level:** warning
- **Source:** phpstan-drupal
- **Ports:** phpstan-drupal's `EntityStorageDirectInjectionRule`

phpstan-drupal reports an entity storage that a constructor takes. Another module can swap the
storage handler of an entity type, so code asks the entity type manager for the storage where it
uses it.

**Reports:**

- a constructor parameter typed as an entity storage. An entity storage type is
  `EntityStorageInterface`, or a class or an interface that extends it and whose name ends in
  `Storage` or `StorageInterface`.

**Not reported:**

- an entity handler's own storage. The entity type manager hands a handler its storage, so a
  `$storage` parameter, or `$storage_controller` as in core's views data, of a class that
  implements `EntityHandlerInterface` is left alone. Any other storage that the handler takes is
  reported;
- as many storage parameters as the parent's constructor takes, under any name, since the class
  has to pass them on;
- a type whose name doesn't end in `Storage` or `StorageInterface`, even when it extends
  `EntityStorageInterface`.

??? example "Example"

    ```php title="modules/example/src/ExampleLister.php"
    // Reported.
    namespace Drupal\example;

    use Drupal\Core\Entity\EntityStorageInterface;

    class ExampleLister {

      public function __construct(
        protected EntityStorageInterface $nodeStorage,
      ) {}

    }

    // Fine.
    namespace Drupal\example;

    use Drupal\Core\Entity\EntityTypeManagerInterface;

    class ExampleLister {

      public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager,
      ) {}

    }
    ```

??? note "Compared with phpstan-drupal"

    - phpstan-drupal also reports an entity handler's own `$storage` parameter.
    - phpstan-drupal takes any type that is an `EntityStorageInterface`, whatever its name.

---

## drupal/entity-storage-property

- **Level:** warning
- **Source:** phpstan-drupal
- **Ports:** phpstan-drupal's `EntityStoragePropertyAssignmentRule`

phpstan-drupal reports an entity storage that the object keeps in a property, for the same reason
as [`drupal/entity-storage-injection`](#drupalentity-storage-injection).

**Reports:**

- a property whose declared type, or `@var` type, is an entity storage. The storage types are the
  same as for `drupal/entity-storage-injection`;
- such a property in a trait.

**Not reported:**

- a promoted constructor parameter, which is reported by `drupal/entity-storage-injection`;
- an entity handler's own `$storage` or `$storage_controller` property. In a trait, `$storage` is
  left alone when every class that uses the trait is an entity handler.

??? example "Example"

    ```php title="modules/example/src/ExampleLister.php"
    // Reported.
    namespace Drupal\example;

    use Drupal\Core\Entity\EntityStorageInterface;
    use Drupal\Core\Entity\EntityTypeManagerInterface;

    class ExampleLister {

      protected EntityStorageInterface $nodeStorage;

      public function __construct(EntityTypeManagerInterface $entity_type_manager) {
        $this->nodeStorage = $entity_type_manager->getStorage('node');
      }

    }

    // Fine.
    namespace Drupal\example;

    use Drupal\Core\Entity\EntityTypeManagerInterface;

    class ExampleLister {

      public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager,
      ) {}

    }
    ```

??? note "Compared with phpstan-drupal"

    - phpstan-drupal reports an assignment of a storage to a property of `$this`. So it also reports
      an untyped property with no `@var` tag that a storage is assigned to, and not a typed property
      that nothing assigns. The check reads the type of the property.
    - phpstan-drupal leaves alone a property declared by a parent class whose constructor takes a
      storage, such as `EntityListBuilder::$storage`. The check leaves alone an entity handler's own
      `$storage`.
    - phpstan-drupal takes any type that is an `EntityStorageInterface`, whatever its name.

---

## drupal/logger-from-factory

- **Level:** error
- **Source:** phpstan-drupal
- **Ports:** phpstan-drupal's `LoggerFromFactoryPropertyAssignmentRule`

phpstan-drupal reports a logger channel that a constructor takes from the logger factory and keeps
on the object, in a class that uses `DependencySerializationTrait`. The trait serializes a service
by its id. A channel from the factory has no id, so the trait can't restore the property. Inject the
channel service itself, such as `logger.channel.example`.

**Reports:**

- a `get()` call on a `LoggerChannelFactoryInterface` whose result is assigned straight to a
  property of `$this`, in the constructor of a class that uses `DependencySerializationTrait`. The
  class can use the trait itself, through a parent class or through another trait.

**Not reported:**

- a channel fetched outside the constructor, which is fetched again after the object is
  unserialized;
- a channel kept in a local variable, and a value computed from a channel;
- a call inside an anonymous class.

??? example "Example"

    ```php title="modules/example/src/ExampleQueue.php"
    // Reported.
    namespace Drupal\example;

    use Drupal\Core\DependencyInjection\DependencySerializationTrait;
    use Drupal\Core\Logger\LoggerChannelFactoryInterface;
    use Drupal\Core\Logger\LoggerChannelInterface;

    class ExampleQueue {

      use DependencySerializationTrait;

      protected LoggerChannelInterface $logger;

      public function __construct(LoggerChannelFactoryInterface $logger_factory) {
        $this->logger = $logger_factory->get('example');
      }

    }

    // Fine.
    namespace Drupal\example;

    use Drupal\Core\DependencyInjection\DependencySerializationTrait;
    use Drupal\Core\Logger\LoggerChannelInterface;

    class ExampleQueue {

      use DependencySerializationTrait;

      public function __construct(
        protected LoggerChannelInterface $logger,
      ) {}

    }
    ```

??? note "Compared with phpstan-drupal"

    phpstan-drupal reports the same assignment, and also in the constructor of an anonymous class
    that uses the trait.

---

## drupal/dependency-serialization-property

- **Level:** error
- **Source:** phpstan-drupal
- **Ports:** phpstan-drupal's `DependencySerializationTraitPropertyRule`

phpstan-drupal reports a property that `DependencySerializationTrait` can't restore. The trait's
`__sleep()` lists the properties that `get_object_vars()` sees in the class that composes the trait,
and its `__wakeup()` writes them back by name. Neither method reaches a private property of another
class, and before PHP 8.4 `__wakeup()` can't write a readonly property declared below the composing
class.

A class composes the trait when its body uses the trait, or a trait that uses it, since a trait
method runs in the scope of the class that uses it. The check looks at every class that uses the
trait, itself, through another trait or through a parent class.

**Reports:**

- a private property of a class that uses `DependencySerializationTrait`, the composing class
  included. When a subclass is serialized, `__sleep()` would have to name that property in its
  mangled form;
- before PHP 8.4, a readonly property whose type can hold an object, declared below the composing
  class. A type of scalars and arrays can't. PHP 8.4 lets the parent's scope write it. As in
  phpstan-drupal, any readonly property of such a type counts, although `__wakeup()` writes only
  the properties that held a service or an entity storage, so a readonly value object such as
  core's `Component::$metadata` is reported too;
- a private property that a trait in the `Drupal\` namespace, named in the class body, brings into
  the class. It is reported at the class, since the trait's file serves other classes;
- in a class that composes the trait itself, below parents that don't, each private property of
  those parents, and before PHP 8.4 each of their readonly properties of such a type. These are
  reported at the class, once per property, naming the parent. The trait's `__sleep()` never lists
  them.

Promoted constructor parameters count. The classes that compose the trait are read from the PHP
files under the Drupal root. Without core on disk, core's three bases stand in: `FormBase`,
`PluginBase` and `EntityHandlerBase`. So core's forms, plugins and entity handlers count, and
controllers, plugin forms and views plugins, which don't use the trait, don't.

**Not reported:**

- a static property, which `get_object_vars()` doesn't list;
- a property of a parent or a trait outside the `Drupal\` namespace, such as Symfony's session
  handler base, PHPUnit's `TestCase` or Prophecy's trait, since the module can't change them. A
  property that a trait in the `Drupal\` namespace brings into a parent counts as the parent's, and
  one that it brings into the class itself as the class's own;
- the parents' properties in a class with a `__sleep()` of its own, or with a `__serialize()`,
  which PHP calls in place of the trait's `__sleep()`.

???+ example "Example"

    ```php title="modules/example/src/ExampleQueue.php"
    // Reported.
    namespace Drupal\example;

    use Drupal\Core\DependencyInjection\DependencySerializationTrait;
    use Drupal\Core\State\StateInterface;

    class ExampleQueue {

      use DependencySerializationTrait;

      public function __construct(
        private StateInterface $state,
      ) {}

    }

    // Fine.
    namespace Drupal\example;

    use Drupal\Core\DependencyInjection\DependencySerializationTrait;
    use Drupal\Core\State\StateInterface;

    class ExampleQueue {

      use DependencySerializationTrait;

      public function __construct(
        protected StateInterface $state,
      ) {}

    }
    ```

??? note "Compared with phpstan-drupal"

    - phpstan-drupal doesn't report the private and readonly properties of the parents of a class
      that composes the trait.
    - phpstan-drupal also reports a private static property.
