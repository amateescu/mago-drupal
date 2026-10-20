# Hooks and modules

Checks for hook implementations, for the files that a module loads with `loadInclude()`, and for
classes that extend the internal classes of another module.

## Hook implementations

The hook checks read an implementation in two forms:

- a method with a `#[Hook('name')]` attribute, or a class with one, which names its method,
  `__invoke()` by default. A method that the class inherits is reported at the class's attribute,
  once for each class that names it;
- a function named `<module>_<hook>` in a procedural file of the module: a `.module`, `.install`,
  `.inc`, `.theme`, `.profile` or `.engine` file. The module is the first part of the file name,
  as in `example.module` or `example.views.inc`.

A procedural function isn't checked when it has a `#[LegacyHook]` or `#[LegacyRequirementsHook]`
attribute, which keeps a procedural implementation next to an OOP one for older core. It isn't
checked either when it is the function marked `#[ProceduralHookScanStop]` or comes after it, since
Drupal stops collecting procedural hooks there.

What a hook declares comes from its documentation: the `hook_*()` functions in the `*.api.php`
files under the Drupal root, core's included. The files are read from disk, not from the analyzed
code.

---

## drupal/deprecated-hook

- **Level:** warning
- **Source:** phpstan-drupal
- **Ports:** phpstan-drupal's `DeprecatedHookImplementation`

phpstan-drupal reports an implementation of a hook whose documentation function is `@deprecated`.
The report names the documentation function, and its `@deprecated` text gives the replacement.

See [Hook implementations](#hook-implementations) for the implementations that are read.

**Reports:**

- an implementation of a hook whose `hook_*()` documentation function has a `@deprecated` tag;
- an implementation of a hook that doesn't have a documentation function of its own name, when a
  deprecated documentation function with an uppercase placeholder matches it, as
  `search_api_query_foo_alter` matches `hook_search_api_query_TAG_alter`;
- an implementation of `hook_field_widget_form_alter()`, `hook_field_widget_multivalue_form_alter()`
  or their `WIDGET_TYPE` forms. Drupal 10 removed these hooks and their documentation, so core never
  calls them. The check knows them from a list of its own, which it uses when the api files document
  the four hooks that replace them, as core's `field.api.php` does. The help names the hook to
  implement instead.

A name can match several placeholders, as `search_api_query_foo_view_alter` also matches core's
`hook_ENTITY_TYPE_view_alter`. The match with the most text outside its placeholders decides. When
several tie, the implementation is reported only if all of them are deprecated.

**Not reported:**

- with `--deprecations=12`, a deprecation whose `@deprecated` text names a Drupal removal version
  after 12. See [Deprecation target](deprecations.md#deprecation-target);
- an implementation of `hook_field_widget_complete_form_alter()`,
  `hook_field_widget_single_element_form_alter()` or their `WIDGET_TYPE` forms, though the name of
  the removed `hook_field_widget_WIDGET_TYPE_form_alter()` matches them too. A documented name
  decides first, and the documented `WIDGET_TYPE` forms have more text outside the placeholder;
- a removed hook that an api file still documents. The api file decides, as for any other hook.

??? example "Example"

    ```php title="modules/other/other.api.php"
    /**
     * Describes the things of other modules.
     *
     * @deprecated in other:2.1.0 and is removed from other:3.0.0. Implement
     *   hook_other_thing_data() instead.
     *
     * @see https://www.drupal.org/node/3000000
     */
    function hook_other_thing_info(): array {
      return [];
    }

    /**
     * Describes the data of the things of other modules.
     */
    function hook_other_thing_data(): array {
      return [];
    }
    ```

    ```php
    // Reported.
    /**
     * Implements hook_other_thing_info().
     */
    function example_other_thing_info(): array {
      return [];
    }

    // Fine.
    /**
     * Implements hook_other_thing_data().
     */
    function example_other_thing_data(): array {
      return [];
    }
    ```

??? note "Compared with phpstan-drupal"

    - phpstan-drupal checks procedural implementations in `.module` and `.inc` files only. The check
      also reads `#[Hook]` methods and classes, and the other procedural files.
    - phpstan-drupal matches a hook by its exact name only. The check also matches the documentation
      functions with placeholders.
    - phpstan-drupal reports `hook_field_widget_multivalue_form_alter` and
      `hook_field_widget_multivalue_WIDGET_TYPE_form_alter` implementations from a list of its own,
      since Drupal 10 removed their documentation. The check's list also has
      `hook_field_widget_form_alter` and `hook_field_widget_WIDGET_TYPE_form_alter`, which Drupal 10
      removed as well. The check uses its list only next to the documentation of the hooks that
      replace them, and phpstan-drupal uses its list without it.
    - phpstan-drupal reads the hook documentation only with `checkCoreDeprecatedHooksInApiFiles` or
      `checkContribDeprecatedHooksInApiFiles` on, which `bleedingEdge.neon` turns on. Without them
      it reports only the two field widget hooks from its own list.
    - phpstan-drupal reads the documentation functions only from the `<module>.api.php` file of each
      module. It misses the hooks documented under `core/lib`, such as `hook_updater_info()`. The
      check reads every `*.api.php` file under the Drupal root.
    - phpstan-drupal also checks a function with `#[LegacyHook]` or `#[LegacyRequirementsHook]`, and
      the functions from `#[ProceduralHookScanStop]` on.
    - phpstan-drupal reports every deprecation. Nothing like `--deprecations=12` exists in
      phpstan-drupal.

---

## drupal/hook-form-alter-signature

- **Level:** error
- **Source:** phpstan-drupal
- **Ports:** phpstan-drupal's `HookFormAlterRule`

phpstan-drupal reports a form alter hook whose parameters don't fit the call. The module handler
calls every form alter hook as `(array &$form, FormStateInterface $form_state, string $form_id)`.
A form alter implementation is an implementation of `hook_form_alter()` or
`hook_form_FORM_ID_alter()`, see [Hook implementations](#hook-implementations).

**Reports:**

- a form alter implementation whose first parameter isn't taken by reference;
- one whose first parameter is typed as something other than an array;
- one whose second parameter is typed as something other than `FormStateInterface`. A type whose
  name ends in `FormStateInterface` or `FormState` counts;
- one whose third parameter is typed as something other than a string;
- one that requires a fourth argument.

**Not reported:**

- the type of an untyped parameter. The first parameter is still checked for the reference;
- a parameter typed as `mixed`, and a form state typed as `object`, which take what the module
  handler passes;
- a fourth parameter that is variadic or has a default;
- an implementation with fewer than three parameters, since PHP drops the extra arguments. Core
  does this in several places.

??? example "Example"

    ```php
    // Reported.
    use Drupal\Core\Form\FormStateInterface;

    /**
     * Implements hook_form_alter().
     */
    function example_form_alter(array $form, FormStateInterface $form_state, string $form_id): void {
    }

    // Fine.
    use Drupal\Core\Form\FormStateInterface;

    /**
     * Implements hook_form_alter().
     */
    function example_form_alter(array &$form, FormStateInterface $form_state, string $form_id): void {
    }
    ```

??? note "Compared with phpstan-drupal"

    - phpstan-drupal checks methods with their own `#[Hook]` attribute only. The check also reads
      class-level `#[Hook]` attributes and procedural implementations.
    - phpstan-drupal reports an implementation with no parameters, and one with more than three,
      a variadic or optional fourth included. The check reports only a required fourth parameter.
    - phpstan-drupal reports a parameter typed as `mixed`, and a form state typed as `object`. The
      check accepts them.
    - phpstan-drupal reports each wrong parameter as an error of its own. The check reports one
      issue for the implementation, and its message lists them.

---

## drupal/hook-entity-operation-cacheability

- **Level:** error
- **Source:** phpstan-drupal
- **Ports:**
    - phpstan-drupal's `HookEntityOperationCacheabilityRule`
    - phpstan-drupal's `ProceduralHookEntityOperationCacheabilityRule`

Drupal 11.3 added a `CacheableMetadata` parameter to `hook_entity_operation()` and
`hook_entity_operation_alter()`. phpstan-drupal reports an implementation that doesn't take it, and
suggests a required `\Drupal\Core\Cache\CacheableMetadata $cacheability`. The check's report
suggests `?\Drupal\Core\Cache\CacheableMetadata $cacheability = NULL`, which keeps the
implementation working on core before 11.3, which doesn't pass such an argument.

**Reports:**

- an implementation of `hook_entity_operation()` whose second parameter isn't typed as
  `CacheableMetadata`;
- an implementation of `hook_entity_operation_alter()` whose third parameter isn't typed as
  `CacheableMetadata`.

A type whose name ends in `CacheableMetadata` or `CacheableDependencyInterface` counts, since that
interface takes the object too. A missing or untyped parameter is reported. See
[Hook implementations](#hook-implementations) for the implementations that are read.

**Not reported:**

- any implementation while the documentation function, `hook_entity_operation()` or
  `hook_entity_operation_alter()` in core's `*.api.php` file, doesn't declare the parameter, as on
  core before 11.3.

??? example "Example"

    ```php title="core/lib/Drupal/Core/Entity/entity.api.php"
    use Drupal\Core\Cache\CacheableMetadata;
    use Drupal\Core\Entity\EntityInterface;

    /**
     * Declares entity operations.
     */
    function hook_entity_operation(EntityInterface $entity, CacheableMetadata $cacheability): array {
      return [];
    }
    ```

    ```php
    // Reported.
    use Drupal\Core\Entity\EntityInterface;

    /**
     * Implements hook_entity_operation().
     */
    function example_entity_operation(EntityInterface $entity): array {
      return [];
    }

    // Fine.
    use Drupal\Core\Cache\CacheableMetadata;
    use Drupal\Core\Entity\EntityInterface;

    /**
     * Implements hook_entity_operation().
     */
    function example_entity_operation(EntityInterface $entity, ?CacheableMetadata $cacheability = NULL): array {
      return [];
    }
    ```

??? note "Compared with phpstan-drupal"

    - phpstan-drupal checks when `Drupal::VERSION` is 11.3 or later. The check reads the
      documentation function instead.
    - phpstan-drupal wants the type to be exactly `Drupal\Core\Cache\CacheableMetadata`. The check
      also accepts `CacheableDependencyInterface`.
    - phpstan-drupal checks procedural implementations in `.module` and `.inc` files only, and
      methods with their own `#[Hook]` attribute. The check also reads the other procedural files
      and class-level `#[Hook]` attributes.
    - phpstan-drupal also checks a function with `#[LegacyHook]` or `#[LegacyRequirementsHook]`, and
      the functions from `#[ProceduralHookScanStop]` on.

---

## drupal/load-include

- **Level:** error, or warning when the module is unknown
- **Source:** phpstan-drupal
- **Ports:** phpstan-drupal's `LoadIncludes`

`ModuleHandlerInterface::loadInclude($module, $type, $name)` loads
`<module directory>/<name>.<type>`, and `$name` defaults to the module. For a file that doesn't
exist, or a module that isn't installed, it returns FALSE and doesn't load anything. phpstan-drupal
reports a call that names a file that doesn't exist.

**Reports:**

- a `loadInclude()` call that names a file that doesn't exist in the module's directory;
- a call that names a module that isn't under the Drupal root, as a warning.

The module, the type and the name must be literal strings, by position or by name, as in
`loadInclude(type: 'inc', module: 'example')`. An empty name stands for the module, as it does for
the module handler.

**Not reported:**

- a call whose module, type or name is computed, which can't be checked;
- test code (any file under a `tests` directory) and hook documentation (`*.api.php` files).

??? example "Example"

    ```php title="modules/example/example.tokens.inc"
    /**
     * Builds the tokens of the example module.
     */
    function example_build_tokens(): array {
      return [];
    }
    ```

    ```php
    // Reported.
    use Drupal\Core\Extension\ModuleHandlerInterface;

    /**
     * Loads the token helpers.
     */
    function example_load_tokens(ModuleHandlerInterface $module_handler): void {
      $module_handler->loadInclude('example', 'inc', 'example.token');
    }

    // Fine.
    use Drupal\Core\Extension\ModuleHandlerInterface;

    /**
     * Loads the token helpers.
     */
    function example_load_tokens(ModuleHandlerInterface $module_handler): void {
      $module_handler->loadInclude('example', 'inc', 'example.tokens');
    }
    ```

??? note "Compared with phpstan-drupal"

    - phpstan-drupal reports a module that can't be found as an error. The check reports it as a
      warning.
    - phpstan-drupal reads the arguments by position only.
    - phpstan-drupal looks for `.<type>` when the name is an empty string. The module handler and
      the check use the module's name.
    - phpstan-drupal takes the first string of a union such as `'a'|'b'`. The check needs one
      literal string.
    - phpstan-drupal also checks test code.

---

## drupal/internal-class-extension

- **Level:** warning
- **Source:** phpstan-drupal
- **Ports:** phpstan-drupal's `ClassExtendsInternalClassRule`

phpstan-drupal reports a class that extends an `@internal` class of another module. Drupal's
backwards compatibility policy lets an internal class change in any release.

**Reports:**

- a class that extends an `@internal` class owned by another module;
- an anonymous class that does the same, owned by the module of the class that it is written in;
- a class outside the `Drupal` namespace that extends any `@internal` class.

The owner of a class in `Drupal\<module>\` is that module, and a module's tests, in
`Drupal\Tests\<module>\`, count as the module. The classes in `Drupal\Core\` are core's.

For core's `ContentEntityDeleteForm`, the report suggests `ContentEntityConfirmFormBase`. For other
core classes, it links the backwards compatibility policy.

**Not reported:**

- a class that extends an internal class of its own module, the module's tests included.

The `@internal` classes are read from the PHP files under `core/lib`, `core/tests` and the `src`
directory of every module, profile and theme before the analysis starts, and Mago's metadata
confirms the flag.

??? example "Example"

    ```yaml title="modules/other/other.info.yml"
    name: Other
    type: module
    core_version_requirement: ^11
    ```

    ```php title="modules/other/src/ThingBuilder.php"
    namespace Drupal\other;

    /**
     * Builds things.
     *
     * @internal
     */
    class ThingBuilder {}
    ```

    ```php title="modules/example/src/ExampleBuilder.php"
    // Reported.
    namespace Drupal\example;

    use Drupal\other\ThingBuilder;

    class ExampleBuilder extends ThingBuilder {}

    // Fine.
    namespace Drupal\example;

    class ExampleBuilder {}
    ```

??? note "Compared with phpstan-drupal"

    - phpstan-drupal counts a class as the parent's module when the first two parts of their
      namespaces match. A module's tests, in `Drupal\Tests\`, are then reported for extending the
      module's internal classes. The check counts them as the module.
    - phpstan-drupal reports every anonymous class that extends an internal class.
