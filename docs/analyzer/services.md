# Services

The analyzer types what the service container hands back, so a lookup such as
`\Drupal::service('entity_type.manager')` gets the class of its service. The checks report lookups
of unknown, private and deprecated services, and service definitions with the wrong number of
arguments.

## Container lookups

`ContainerInterface::get()` is declared to return `?object`. For a lookup whose id is a string
literal or a `Foo::class` constant, the analyzer types the result as the class of the service.

**Typed:**

- `get()` on any implementation of Symfony's `ContainerInterface`. That covers Drupal's container
  interfaces, `ContainerBuilder` and the `$this->container` of kernel tests;
- `\Drupal::service()`;
- `\Drupal::classResolver('id')` and `ClassResolverInterface::getInstanceFromDefinition('id')`.
  They try the argument as a service id first and as a class name second, as `ClassResolver` does;
- `\Drupal::classResolver()` with no argument, as `ClassResolverInterface`, as phpstan-drupal types
  it. Without that, Mago turns the declared `ClassResolverInterface|object` into `object`;
- an id that no services file or provider defines, but that names a class. The container registers
  hook classes and other autowired services under their class name, with no line in a services
  file, and `get()` returns that service or throws.

**Not typed:**

- `get()` on a value typed only as PSR-11's `ContainerInterface`. Other libraries implement that
  interface for containers of their own;
- `has()`. A services file on disk means that the module exists, not that it is installed, so
  `if ($container->has('optional.service'))` has to stay a real branch.

A literal second argument other than `ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE` keeps
`null` in the type, because the container then returns null for a missing service. A computed
second argument keeps the declared type.

```php
$container->get('entity_type.manager');            // Drupal\Core\Entity\EntityTypeManager
$container->get(EntityTypeManagerInterface::class); // the same, through the interface alias
$container->get('cache.default');                   // Drupal\Core\Cache\CacheBackendInterface
$container->get('kernel');                          // Drupal\Core\DrupalKernelInterface
$container->get(MyModuleHooks::class);              // MyModuleHooks, with no line in a services file
$container->get('no.such.service');                 // stays ?object
```

!!! warning "Leave Mago's own `psr-container` plugin off"

    Mago asks its built-in plugins before the extension, so with that plugin on, it types every
    `get(Foo::class)` on a Drupal container as `Foo`. That plugin reads only the first argument, so
    the `null` that a second argument adds is lost. It also skips the service index:

    - an alias gets the interface that it is named after instead of the class of the service;
    - a service that an optional module decorates keeps its own class instead of the type that it
      shares with the decorator.

## Container parameters

`ContainerInterface::getParameter()` is declared to return
`array|bool|string|int|float|UnitEnum|null`. For a literal name that a `parameters:` section of the
services files defines, the analyzer types the result by the kind of value written there: `string`,
`bool`, `int`, `float`, or `array<int|string, mixed>` for a list or a mapping. When the files give
one name values of different kinds, the type is their union. This covers every container that the
lookups cover, and `\Drupal::getContainer()`.

The value itself isn't used. `settings.php`, a site's own services file and service providers change
values, and `app.root` and `site.path` are empty strings that the kernel replaces.

**Not typed:**

- a name whose value is `null`, one `%name%` reference to another parameter, a `'@id'` service
  reference or a custom tag;
- a name that isn't defined in any services file.

```php
$container->getParameter('app.root');                   // string
$container->getParameter('security.enable_super_user'); // bool
$container->getParameter('renderer.config');            // array<int|string, mixed>
$container->getParameter('password.algorithm');         // the declared union, since it is ~ in YAML
```

## The service index

The analyzer reads the services without booting Drupal, from two sources.

**Services files.** Every `*.services.yml` file of core, and of each module and profile under the
Drupal root, that has a `*.info.yml` file of the same name next to it. That skips scaffold templates
and the copies in test fixtures. The analyzer resolves these the way the container compiler does:

- `alias:` and the `'@id'` shorthand;
- `parent:`, including the parent's `deprecated:`, and `abstract:` templates;
- a class name as the id, with no `class:` key;
- `deprecated:` messages;
- `decorates:`, see [Decorators](#decorators).

It also adds the synthetic `kernel`, `class_loader` and `service_container` services. The
`parameters:` sections of the same files give the parameter kinds.

A site's own `services.yml` under `sites/` isn't read. `settings.php` picks the files that the
container loads, through `container_yamls`, and the analyzer doesn't run it. Such a file only
overrides the values of parameters whose kinds are already fixed by core and the modules.

**Service providers.** The `*ServiceProvider.php` classes in the analyzed `[source] paths`, through
Mago's codebase scan. The provider files under the root's `core/lib` and the `src` directories of
extensions are also read from disk for the ids that they register, so a core service that exists
only in PHP counts as defined in a contrib workspace too, with its type left alone. The analyzer
reads these calls when their arguments are literals:

- `$container->register('id', Foo::class)` and `->register('id')->setClass(...)`;
- `->setDefinition('id', new Definition(Foo::class))`;
- `->setAlias('alias', 'id')`.

A literal id with a computed class is kept as a service of unknown type, so the id counts as
defined. A provider's registration overrides the one from YAML, which is the order the container
applies them in.

Only the providers registered by Drupal are read: core's `CoreServiceProvider`, and a module's
`src/<Module>ServiceProvider.php`. The installer's providers run only during an install, and a test
module's provider only in tests. A test module's services file adds its own services, and doesn't
replace one defined by core or another module.

### Visibility

Drupal's `ContainerBuilder::register()` and `setAlias()` make what they create public. A
`new Definition()` handed to `setDefinition()` keeps Symfony's default, which is private since
Symfony 5.2. Such a service is private unless `setPublic(TRUE)` is called on it:

- on the chain that builds the definition;
- on the definition that `setDefinition()` returns;
- on the variable that holds the definition, in the same function.

A literal `setPublic(FALSE)` makes any of them private, a `register()` chain included. A
`new ChildDefinition('parent')` takes the class and the visibility of its parent, unless it sets its
own. A definition that the scan can't follow, such as one that another method builds or finishes,
counts as public, and so does a variable reused for several definitions once any of them is made
public. Visibility is read from the providers in the analyzed paths. The ids read from disk count as
public.

Drupal 9.2 deprecated leaving the visibility out, and since Drupal 10 such a definition stays
private. Core 11.4 doesn't raise a deprecation for it, so the only report is
[`drupal/unknown-service`](#drupalunknown-service) on a lookup.

### Decorators

The analyzer applies `decorates:` the way the container compiler does:

- the decorated id, and every alias of it, returns the class of the decorator;
- what the id pointed at before is reachable as `<decorator id>.inner`, or under the
  `decoration_inner_name`;
- when several services decorate one id, the highest `decoration_priority` applies first, and the
  id returns the lowest one. Between equal priorities, the last one defined wins;
- a decorator of a missing id is dropped with `decoration_on_invalid: ignore`, and takes the id over
  with `decoration_on_invalid: ~`.

A decorator replaces a service only while the module that declares it is enabled. A decorator from
core's services file, from a module whose info file says `required: true`, or from the service's
own module is always there, and the id returns its class. A decorator from another module, such as
Workspaces decorating `cron`, may be off on the site. The id then gets the type that both classes
share: the decorated class when the decorator extends it, otherwise the most specific classes and
interfaces that they both have, such as `CronInterface`. That is an intersection when there are
several, and the declared type when they don't share any.

A decorator whose class isn't in the codebase, such as one from a module that the run doesn't
analyze, leaves the id its own class. Otherwise one contrib module that decorates a core service
would turn every core caller of that id into a bare `object`.

### Not indexed

These keep the declared type:

- a service whose class exists only at runtime: a `factory:` without `class:`, or a `%parameter%`
  class;
- a service whose class hasn't been scanned by Mago;
- anything that a `ServiceModifierInterface::alter()` changes.

---

## drupal/deprecated-service

- **Level:** warning
- **Source:** phpstan-drupal
- **Ports:** phpstan-drupal's `GetDeprecatedServiceRule` and `StaticServiceDeprecatedServiceRule`

phpstan-drupal reports a lookup of a service that its definition marks as deprecated. Mago's own
deprecation checks read `@deprecated` on PHP code, and a service deprecation is in YAML. The report
has the message of the definition, which names the replacement.

**Reports:**

- a `get()`, `\Drupal::service()` or class resolver lookup of a service whose definition, or its
  `parent:`, has a `deprecated:` key.

**Not reported:**

- `has()`, which code uses to probe for a service without creating it;
- test code (any file under a `tests` directory) and hook documentation (`*.api.php` files);
- with `--deprecations=12`, a deprecation whose message names a Drupal removal version after 12.
  See [Deprecation target](deprecations.md#deprecation-target).

??? example "Example"

    ```yaml title="modules/example/example.services.yml"
    services:
      example.old_greeter:
        class: Drupal\example\Greeter
        deprecated: The "%service_id%" service is deprecated in example:1.2.0 and is removed from example:2.0.0. Use example.greeter instead. See https://www.drupal.org/node/3000000
      example.greeter:
        class: Drupal\example\Greeter
    ```

    ```php title="modules/example/src/Greeter.php"
    namespace Drupal\example;

    class Greeter {}
    ```

    ```php
    // Reported.
    function example_greeter(): object {
      return \Drupal::service('example.old_greeter');
    }

    // Fine.
    function example_greeter(): object {
      return \Drupal::service('example.greeter');
    }
    ```

??? note "Compared with phpstan-drupal"

    - phpstan-drupal checks `get()` only where the method it finds is declared by Symfony's
      `ContainerInterface`. A call on a value typed as a container class, such as Drupal's
      `Container` or `ContainerBuilder`, isn't checked there, since that class or its Symfony parent
      declares `get()`. The check covers every container.
    - phpstan-drupal doesn't check the class resolver.
    - phpstan-drupal checks a string literal id. The check also takes a `Foo::class` id.
    - phpstan-drupal also reports in test code.

---

## drupal/unknown-service

- **Level:** warning
- **Source:** Drupal's service container

The container throws a `ServiceNotFoundException` for an id that it doesn't have. A compiled
container leaves out the ids that no services file or provider defines, and the private services.

**Reports:**

- a `get()` or `\Drupal::service()` lookup of a string id that no services file or provider
  defines;
- a lookup of a private service:
    - a service with `public: false`, set on it, inherited from its `parent:` or from the
      `_defaults` of its file;
    - the `.inner` id that a decorator moves a service to;
    - a service that a provider registers as private, see [Visibility](#visibility).

**Not reported:**

- an alias, unless the alias says `public: false` itself or through the `_defaults` of its file. A
  plain alias of a private service works;
- a `Foo::class` id. The container registers hook classes and other autowired services under their
  class name, with no line in a services file;
- a lookup with a second argument other than `ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE`,
  which asks for null on purpose;
- an id whose first segment doesn't name a module in the codebase or start a known id, such as
  `persistent_login.token_manager`. It belongs to a module that the site may not have, so a typo in
  the first segment goes unreported too. In Drupal core, these ids are checked, since core looks up
  only its own services;
- any id while core's services aren't in the index;
- test code and hook documentation in `*.api.php` files.

??? example "Example"

    ```yaml title="modules/example/example.services.yml"
    services:
      example.greeter:
        class: Drupal\example\Greeter
    ```

    ```php title="modules/example/src/Greeter.php"
    namespace Drupal\example;

    class Greeter {}
    ```

    ```php
    // Reported.
    function example_greeter(): object {
      return \Drupal::service('example.greter');
    }

    // Fine.
    function example_greeter(): object {
      return \Drupal::service('example.greeter');
    }
    ```

??? note "Compared with phpstan-drupal"

    phpstan-drupal doesn't report an unknown or a private service id.

---

## drupal/service-argument-count

- **Level:** error, or warning for extra arguments
- **Source:** Drupal's service container

When the container builds a service with fewer arguments than the constructor of its class
requires, PHP throws an `ArgumentCountError`. PHP drops the arguments that a constructor doesn't
take.

**Reports:**

- a service definition with fewer `arguments:` than the constructor of its class requires;
- a service definition with more `arguments:` than the constructor takes, as a warning. Any argument
  to a class without a constructor counts as extra.

The report is on the constructor, or on the class when it inherits the constructor, and names the
service id and its services file. The count follows the container:

- a `parent:` service's arguments come first, and a child's are appended. `index_N` replaces one of
  them;
- `!tagged_iterator`, `!tagged_locator` and `!service_closure` count as one argument each, and the
  `.inner` of a decorator counts like any other;
- the `http_middleware`, `session_handler_proxy` and `service_id_collector` tags add the argument
  that their compiler pass passes;
- parameters with defaults make a range, and a variadic parameter lifts the maximum.

**Not reported:**

- a service whose class is outside the analyzed code. The report is on the class, so a module's
  services file that names a core or contrib class outside the run isn't checked;
- extra arguments to a constructor that reads them through `func_get_args()`;
- autowired services: on the definition, on its parent, or through the `_defaults` of the file for a
  definition without a parent;
- named arguments, factories, synthetic services and abstract services;
- services that a provider registers, and ids that a `*ServiceProvider.php` or `*Pass.php` file
  under the root registers, fetches or removes with a literal id;
- a class with a constructor that isn't public, or with an ancestor not scanned by Mago;
- test code.

???+ example "Example"

    ```yaml title="modules/example/example.services.yml"
    # Reported.
    services:
      example.greeter:
        class: Drupal\example\Greeter

    # Fine.
    services:
      example.greeter:
        class: Drupal\example\Greeter
        arguments: ['@config.factory']
    ```

    ```php title="modules/example/src/Greeter.php"
    namespace Drupal\example;

    use Drupal\Core\Config\ConfigFactoryInterface;

    class Greeter {

      public function __construct(
        protected ConfigFactoryInterface $configFactory,
      ) {}

    }
    ```

??? note "Compared with phpstan-drupal"

    phpstan-drupal doesn't check the arguments of a service definition.
