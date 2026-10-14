# Plugins

The analyzer types the plugins created by core's plugin managers and the definitions read by
plugins. The checks report unknown plugin ids, plugin managers that never call `alterInfo()` or
`setCacheBackend()`, and plugin annotations with the `context` key removed in Drupal 9.

## Plugin instances

`$manager->createInstance('id')` returns the plugin class for core's attribute-based managers:
blocks, actions, conditions, field types, widgets, formatters, layouts, mail, queue workers, render
elements, typed data, filters, image effects, media sources, REST resources and the other managers
whose constructor names an attribute class. A contrib manager that extends one of them, or a
receiver typed by the manager's interface, is recognised through the class ancestry.

```php
$blockManager->createInstance('page_title_block');       // Drupal\Core\Block\Plugin\Block\PageTitleBlock
$blockManager->createInstance('system_menu_block:main'); // Drupal\system\Plugin\Block\SystemMenuBlock
$blockManager->createInstance('no_such_block');          // Broken, and reported as drupal/unknown-plugin
```

- A `base:derivative` id resolves through its base plugin, the longest one declared, since a base
  can hold a colon itself, as core's `entity:save_action` does. That is wrong for a deriver that
  sets a `class` per derivative.
- On a fallback manager (blocks, entity reference selection and filters), an unknown plain id is
  typed as the fallback plugin, since that is what runs. A derivative id whose base nothing declares
  keeps the declared type, since the base may come from YAML or a deriver that the index doesn't
  read.

## The plugin index

The plugin classes are read from Mago's class metadata: every instantiable class that descends
from `PluginInspectionInterface`, or from the mail, archiver, config action and language
negotiation method interfaces, which don't extend it, and that has one of those attributes or a
subclass of one. Vendor code counts, and field items reach the index through `TypedData`.

The id is the attribute's literal `id`, or the string that a class constant it names holds, as in
`id: LanguageNegotiationUrl::METHOD_ID`. Two classes that declare the same id cancel each other out.

Contrib still ships legacy docblock annotations such as `@Block(id = "…")` or `@RenderElement("…")`.
The analyzer reads them from the `src/Plugin/**/*.php` and `src/Element/*.php` files of every
extension under the Drupal root, analyzed or in `includes`, from disk through the cache. An
annotation maps to the attribute of the same short name, and `@SearchPlugin` and `@FormElement` are
mapped by hand. The legacy `@ContentEntityType` and `@ConfigEntityType` annotations are read the
same way, see [The entity type index](entities.md#the-entity-type-index).

- An analyzed file that has an annotation on disk is read from the text analyzed by Mago, so an edit
  in an editor buffer that hasn't been saved counts.
- An annotation added to a file that had none when the workers started is read once the file is
  saved.
- An annotated file outside the Drupal root isn't read.

An annotation doesn't count on an abstract class, in a `@code` sample, or on a class that also has
the attribute. Two annotated classes that claim one id cancel each other out, as attributed ones do.

## Not modelled

These keep the declared types:

- the changes that a `hook_*_info_alter()` makes to definitions;
- the managers whose attribute isn't known to the analyzer;
- plugins discovered from YAML, such as menu links.

## Plugin definitions

`PluginBase` documents `$pluginDefinition` and `getPluginDefinition()` as
`array|PluginDefinitionInterface`, so Mago reports every `$this->pluginDefinition['label']` as
`invalid-array-access` for the object half. Arrays are Drupal's default: annotation, attribute and
YAML discovery all produce them, unless the plugin type opts into a definition class.

The analyzer drops that report on a plugin's own definition, read through the property or the
getter. It keeps the report:

- on a layout, a Layout Builder section storage or a CKEditor 5 plugin, which have definition
  objects in core;
- on a class with a discovery attribute whose `get()` returns an object;
- on the generic bases that every plugin type extends, both `PluginBase` classes and
  `ConfigurablePluginBase`, since a plugin of any type may be `$this` there.

```php
$this->pluginDefinition['label'];       // no invalid-array-access on a block, local task or filter
$this->getPluginDefinition()['label'];  // the same through the getter
$block->getPluginDefinition();          // array<string, mixed> on a BlockPluginInterface
```

`getPluginDefinition()` on another plugin returns `array<string, mixed>` when the type of the
receiver names a plugin type with array definitions: an interface that extends
`PluginInspectionInterface`, other than the context-aware ones that layouts and section storage
share, or a discovery attribute. That covers a definition held in a variable too. A receiver typed
as `PluginInspectionInterface` or `PluginBase` keeps core's union, since any plugin type fits. In a
trait, `$this->getPluginDefinition()` is an array when every class that uses the trait has array
definitions, so `BlockPluginTrait` gets one, and `ContextAwarePluginTrait`, which layouts use too,
keeps the union.

### Derivers

A deriver gets its base definition documented the same way, so inside a class that implements
`DeriverInterface` every such report goes, whatever the variable is called. A deriver for a plugin
type with definition objects works with them through their methods. A plugin manager's
`$definition` in `processDefinition()` or in an alter keeps the report.

---

## drupal/unknown-plugin

- **Level:** warning
- **Source:** Drupal's plugin managers

A plugin manager throws a `PluginNotFoundException` for an id that no plugin declares. A fallback
manager, such as the block manager, returns its fallback plugin instead, which doesn't do what the
code expects either.

**Reports:**

- a `createInstance()` call with a literal plain id that no scanned plugin declares.

The check covers the plugin types that have a core plugin to look for: actions, archivers, blocks,
conditions, entity reference selection, field types, formatters, widgets, mail, filters, image
effects, media sources and REST resources.

**Not reported:**

- a `base:derivative` id, which comes from a deriver;
- an id while the core plugin of its type isn't in the index. Without it, core is outside the
  analyzed code;
- a layout id, since core's layouts are mostly YAML, which the index doesn't hold;
- test code (any file under a `tests` directory) and hook documentation (`*.api.php` files).
  Tests mock managers.

??? example "Example"

    ```php title="modules/example/src/Plugin/Block/ExampleBlock.php"
    namespace Drupal\example\Plugin\Block;

    use Drupal\Core\Block\Attribute\Block;
    use Drupal\Core\Block\BlockBase;

    #[Block(id: 'example_block')]
    class ExampleBlock extends BlockBase {

      public function build(): array {
        return [];
      }

    }
    ```

    ```php
    // Reported.
    use Drupal\Core\Block\BlockManagerInterface;

    function example_block(BlockManagerInterface $manager): object {
      return $manager->createInstance('example_blok');
    }

    // Fine.
    use Drupal\Core\Block\BlockManagerInterface;

    function example_block(BlockManagerInterface $manager): object {
      return $manager->createInstance('example_block');
    }
    ```

??? note "Compared with phpstan-drupal"

    phpstan-drupal doesn't report an unknown plugin id.

---

## drupal/plugin-manager-alter-info

- **Level:** warning
- **Source:** phpstan-drupal
- **Ports:** phpstan-drupal's `PluginManagerInspectionRule`, the half for `alterInfo()`

phpstan-drupal reports a plugin manager that never calls `alterInfo()`. Without the call, other
modules can't alter the manager's plugin definitions.

**Reports:**

- a `DefaultPluginManager` subclass in the analyzed code whose own constructor never calls
  `alterInfo()`.

The calls are read from the tokens of the constructor, so a call in a comment doesn't count. A
`parent::__construct()` call is followed into the parent's constructor, read from disk, so a manager
that leaves the call to its parent isn't reported.

**Not reported:**

- a manager whose service calls `alterInfo()` through `calls:`, or through the `calls:` of its
  `parent:` service;
- a class without a constructor of its own;
- an abstract class;
- test code (any file under a `tests` directory);
- a class whose constructor, or a parent constructor that it calls, can't be read from disk. See
  [Finding the Drupal root](../setup/worker-arguments.md#finding-the-drupal-root).

??? example "Example"

    ```php title="modules/example/src/ExampleManager.php"
    // Reported.
    namespace Drupal\example;

    use Drupal\Core\Cache\CacheBackendInterface;
    use Drupal\Core\Extension\ModuleHandlerInterface;
    use Drupal\Core\Plugin\DefaultPluginManager;

    class ExampleManager extends DefaultPluginManager {

      public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
        parent::__construct('Plugin/Example', $namespaces, $module_handler);
        $this->setCacheBackend($cache_backend, 'example_plugins');
      }

    }

    // Fine.
    namespace Drupal\example;

    use Drupal\Core\Cache\CacheBackendInterface;
    use Drupal\Core\Extension\ModuleHandlerInterface;
    use Drupal\Core\Plugin\DefaultPluginManager;

    class ExampleManager extends DefaultPluginManager {

      public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
        parent::__construct('Plugin/Example', $namespaces, $module_handler);
        $this->alterInfo('example_info');
        $this->setCacheBackend($cache_backend, 'example_plugins');
      }

    }
    ```

??? note "Compared with phpstan-drupal"

    - phpstan-drupal checks every class that implements `PluginManagerInterface` and extends
      another class, abstract classes included. The check takes `DefaultPluginManager` subclasses
      that aren't abstract.
    - phpstan-drupal looks for the call in the constructor alone. The check also follows
      `parent::__construct()` and reads the `calls:` of the manager's services.
    - phpstan-drupal skips a class whose name contains "test". The check skips the files under a
      `tests` directory.
    - phpstan-drupal also reports a manager with YAML discovery that calls `parent::__construct()`.
      The check doesn't port that half: it reports core's own `LayoutPluginManager` and
      `CKEditor5PluginManager`.

---

## drupal/plugin-manager-cache-backend

- **Level:** warning
- **Source:** phpstan-drupal
- **Ports:** phpstan-drupal's `PluginManagerSetsCacheBackendRule`

phpstan-drupal reports a plugin manager that never sets a cache backend. Without one, plugin
discovery runs on every request.

**Reports:**

- a `DefaultPluginManager` subclass in the analyzed code whose own constructor never calls
  `setCacheBackend()`.

The calls are read as for
[`drupal/plugin-manager-alter-info`](#drupalplugin-manager-alter-info), with `parent::__construct()`
followed into the parent's constructor. A `setCacheBackend()` call without the cache key counts as
a call. It is Mago's own `too-few-arguments`, since core requires the key.

**Not reported:**

- a manager whose service calls `setCacheBackend()` through `calls:`, or through the `calls:` of its
  `parent:` service;
- a class without a constructor of its own;
- an abstract class;
- test code (any file under a `tests` directory);
- a class whose constructor, or a parent constructor that it calls, can't be read from disk.

??? example "Example"

    ```php title="modules/example/src/ExampleManager.php"
    // Reported.
    namespace Drupal\example;

    use Drupal\Core\Cache\CacheBackendInterface;
    use Drupal\Core\Extension\ModuleHandlerInterface;
    use Drupal\Core\Plugin\DefaultPluginManager;

    class ExampleManager extends DefaultPluginManager {

      public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
        parent::__construct('Plugin/Example', $namespaces, $module_handler);
        $this->alterInfo('example_info');
      }

    }

    // Fine.
    namespace Drupal\example;

    use Drupal\Core\Cache\CacheBackendInterface;
    use Drupal\Core\Extension\ModuleHandlerInterface;
    use Drupal\Core\Plugin\DefaultPluginManager;

    class ExampleManager extends DefaultPluginManager {

      public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
        parent::__construct('Plugin/Example', $namespaces, $module_handler);
        $this->alterInfo('example_info');
        $this->setCacheBackend($cache_backend, 'example_plugins');
      }

    }
    ```

??? note "Compared with phpstan-drupal"

    - phpstan-drupal checks every class that implements `PluginManagerInterface`, abstract classes
      included. The check takes `DefaultPluginManager` subclasses that aren't abstract.
    - phpstan-drupal looks for the call among the top-level statements of the constructor, so a call
      inside an `if` is missed there. The check finds the call anywhere in the constructor, follows
      `parent::__construct()`, and reads the `calls:` of the manager's services.
    - phpstan-drupal counts a call with fewer than two arguments as missing.
    - phpstan-drupal also reports in test code.

---

## drupal/plugin-annotation-context

- **Level:** error
- **Source:** phpstan-drupal
- **Ports:** phpstan-drupal's `PluginAnnotationContextDefinitionsRule`

phpstan-drupal reports a plugin annotation that declares its contexts under the `context` key.
Drupal 8.7 renamed the key to `context_definitions`, and Drupal 9 removed the old one.

**Reports:**

- a plugin annotation with a `context` key.

The annotation is read as [the plugin index](#the-plugin-index) reads it.

**Not reported:**

- an annotation that doesn't count for the index, such as one on an abstract class or on a class
  that also has the attribute.

??? example "Example"

    ```php title="modules/example/src/Plugin/Block/ExampleBlock.php"
    // Reported.
    namespace Drupal\example\Plugin\Block;

    use Drupal\Core\Block\BlockBase;

    /**
     * Shows the title of the node.
     *
     * @Block(
     *   id = "example_title",
     *   admin_label = @Translation("Title"),
     *   context = {
     *     "node" = @ContextDefinition("entity:node"),
     *   },
     * )
     */
    class ExampleBlock extends BlockBase {

      public function build(): array {
        return [];
      }

    }

    // Fine.
    namespace Drupal\example\Plugin\Block;

    use Drupal\Core\Block\BlockBase;

    /**
     * Shows the title of the node.
     *
     * @Block(
     *   id = "example_title",
     *   admin_label = @Translation("Title"),
     *   context_definitions = {
     *     "node" = @ContextDefinition("entity:node"),
     *   },
     * )
     */
    class ExampleBlock extends BlockBase {

      public function build(): array {
        return [];
      }

    }
    ```

??? note "Compared with phpstan-drupal"

    - phpstan-drupal checks a class that implements `ContextAwarePluginInterface`. The check takes
      any class with a plugin annotation that the index reads.
    - phpstan-drupal looks for `context = {` anywhere in the text of the docblock. The check reads
      the parsed annotation.
