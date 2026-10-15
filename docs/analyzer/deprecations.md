# Deprecations

Where the analyzer leaves Drupal's deprecations alone, how to report only what is removed in the
next Drupal major, and the checks for the deprecations not reported by Mago.

## Deprecation scopes

Mago reports every use of a deprecated symbol. Drupal has places where that use is the point of the
code, or is allowed, and the analyzer drops the report there. Four markers open a scope:

- a `@group legacy` docblock on a class or a method in test code (any file under a
  `tests` directory). The group is for the test runner, so it doesn't mark anything elsewhere;
- a `@deprecated` docblock on a class-like or a function, since deprecated code may use other
  deprecated code. phpstan-deprecation-rules skips the same places, and Mago 1.57.0 and later skip
  their own deprecation reports in a `@deprecated` function, method or class-like as well;
- a PHPUnit `#[IgnoreDeprecations]` attribute on a class or a method;
- the arguments of `DeprecationHelper::backwardsCompatibleCall()`, whose deprecated branch is what
  runs on older core.

A marker on a class covers the whole class. A marker on a method covers that method's body, and
stops at its closing brace. A `@deprecated` property or constant doesn't open a scope.

A method that overrides a deprecated method is a scope too, whatever its own docblock says, unless
it says `@not-deprecated`. PHPStan counts such a method as deprecated, and a decorator that forwards
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

The scopes apply to Mago's `deprecated-class`, `deprecated-class-constant`, `deprecated-closure`,
`deprecated-constant`, `deprecated-function`, `deprecated-method`, `deprecated-property` and
`deprecated-trait`, and to the analyzer's own
[`drupal/deprecated-original`](entities.md#drupaldeprecated-original),
[`drupal/deprecated-class`](#drupaldeprecated-class) and
[`drupal/deprecated-method`](#drupaldeprecated-method). Mago's `deprecated-feature` is left alone,
since it is about PHP language features, not about the Drupal API that a legacy test exercises.

??? note "How the scopes are found"

    The scopes are read from the file's own bytes with PHP's tokenizer. A file that doesn't hold any
    of the four markers is screened out by a substring test, and Mago batches the issues of a file
    into one request, so a marked file is tokenized once.

    The override scope takes the codebase, so it runs only for an issue that isn't covered by a
    marker. The tokens name the method and its class-like, and the class's ancestors say whether one
    of them declares the method deprecated. Mago analyzes a trait once, on its own, so a trait
    method counts when every class that gets it from the trait inherits a deprecated declaration.
    Finding those classes is a search by method name across the codebase, and only a trait pays for
    it.

??? note "Compared with phpstan-drupal"

    - phpstan-drupal counts `@group legacy` on any class or function, also outside test code.
    - phpstan-drupal counts `#[IgnoreDeprecations]` only without arguments. The analyzer counts the
      attribute with any arguments.
    - phpstan-drupal covers only the `$deprecatedCallable` argument of
      `DeprecationHelper::backwardsCompatibleCall()`. The analyzer covers every argument of the
      call.

## Deprecation target

`--deprecations=12` on the worker command reports only the Drupal deprecations removed in Drupal 12
or an earlier major. A project getting ready for Drupal 12 can leave the ones removed in Drupal 13
for later. This is the "fix now" list of upgrade_status.

```toml
[extension-hosts.drupal]
command = ["php", "-d", "xdebug.mode=off", "-d", "pcov.enabled=0", "vendor/amateescu/mago-drupal/resources/worker.php", "--deprecations=12"]
```

The removal version comes from the `@deprecated` text, in Drupal's form:
`in drupal:11.4.0 and is removed from drupal:13.0.0.` Text that doesn't name a Drupal removal
version is always reported. That covers a contrib module's own deprecations, as in
`removed from webform:7.0.0`, and the deprecations of Symfony and PHP.

Mago's issue names the deprecated symbol but not its `@deprecated` text, which isn't kept by Mago.
The analyzer looks up the declaration of the symbol and reads the docblock above it from the file. A
declaration that can't be read keeps its issue, such as a PHP function from Mago's built-in stubs.

The target applies to these codes:

- Mago's `deprecated-class`, `deprecated-class-constant`, `deprecated-constant`,
  `deprecated-function`, `deprecated-method`, `deprecated-property` and `deprecated-trait`;
- [`drupal/deprecated-hook`](hooks.md#drupaldeprecated-hook), from the `@deprecated` text of the
  hook's documentation in its `*.api.php` file;
- [`drupal/deprecated-service`](services.md#drupaldeprecated-service), from the `deprecated:`
  message of the service;
- [`drupal/deprecated-original`](entities.md#drupaldeprecated-original), which is removed in
  Drupal 12;
- [`drupal/deprecated-class`](#drupaldeprecated-class) and
  [`drupal/deprecated-method`](#drupaldeprecated-method), from the `@deprecated` text read with the
  symbol.

A filter can only keep or drop an issue, so Mago's message still names only the symbol. The
replacement is in the `@deprecated` text at the declaration.

## Deprecations not reported by Mago

Mago reports a deprecated function, method, constant, class constant, property and trait, and a
deprecated class where it is instantiated or extended. A method is deprecated for Mago only where
its own docblock says so, so a call to an implementation that says `{@inheritdoc}` goes unreported.
The analyzer reports more of what is checked by phpstan-deprecation-rules, for the symbols marked
`@deprecated` in Drupal, as [`drupal/deprecated-class`](#drupaldeprecated-class) and
[`drupal/deprecated-method`](#drupaldeprecated-method).

The symbols come from the PHP files under `core/lib`, `core/tests` and the `src` directory of every
module, profile and theme, read before the analysis starts. The `@deprecated` text read with each
one is the help of the issue. Vendor code isn't read, so a deprecated Symfony or Drush interface
isn't reported.

A `@phpstan-ignore` comment doesn't drop these reports, since the `phpstan-ignores` plugin runs
before them.

---

## drupal/deprecated-class

- **Level:** warning
- **Source:** phpstan-deprecation-rules

phpstan-deprecation-rules reports a use of a deprecated class, interface, trait or enum. Mago
reports a deprecated class where it is instantiated, extended or used as a trait, and an interface
that extends a deprecated interface. The check reports the other uses.

**Reports:**

- a class or an enum that implements a deprecated interface;
- a deprecated class-like in a native parameter, return or property type, or in a `catch`;
- a constant of a deprecated class-like, such as `DateTimeRangeConstantsInterface::BOTH`;
- a static call on a deprecated class-like to a method that isn't deprecated itself. Mago reports a
  call to a deprecated method.

**Not reported:**

- a type in a docblock, since only native types are checked;
- a call through `parent::` to a method of a deprecated parent, which is left to Mago's report on
  the `extends`;
- a deprecated constant of a deprecated class-like, which is left to Mago's
  `deprecated-class-constant`, so the access is reported once;
- a class-like that only vendor code declares deprecated;
- a use in a [deprecation scope](#deprecation-scopes), and one that the
  [deprecation target](#deprecation-target) leaves for later.

??? example "Example"

    ```php title="modules/example/src/DisplayConstants.php"
    namespace Drupal\example;

    /**
     * The display options.
     *
     * @deprecated in example:1.2.0 and is removed from example:2.0.0. Use
     *   \Drupal\example\DisplayOptions instead.
     *
     * @see https://www.drupal.org/node/3000000
     */
    interface DisplayConstants {

      const BOTH = 'both';

    }
    ```

    ```php title="modules/example/src/DisplayOptions.php"
    namespace Drupal\example;

    /**
     * The display options.
     */
    final class DisplayOptions {

      const BOTH = 'both';

    }
    ```

    ```php
    // Reported.
    use Drupal\example\DisplayConstants;

    /**
     * Returns the default display.
     */
    function example_default_display(): string {
      return DisplayConstants::BOTH;
    }

    // Fine.
    use Drupal\example\DisplayOptions;

    /**
     * Returns the default display.
     */
    function example_default_display(): string {
      return DisplayOptions::BOTH;
    }
    ```

??? note "Compared with phpstan-deprecation-rules"

    - phpstan-deprecation-rules also reports a call to an instance method of a deprecated
      class-like, as in `$old->run()`, and a static property of one. Neither Mago nor the check
      reports them. On core, almost every such call is on `$this` in a class that extends a
      deprecated class, which is reported once by Mago, at the `extends`.
    - phpstan-deprecation-rules reports a deprecated class-like in a property type, in a `catch` and
      in an enum's `implements` only when PHPStan's bleedingEdge is on. The check always reports
      them.
    - phpstan-deprecation-rules also reports a deprecated class-like in a docblock type, such as a
      `@param` tag on an untyped parameter. The check reads native types only.
    - phpstan-deprecation-rules reads the deprecations of every package. The check reads only
      Drupal's files.

---

## drupal/deprecated-method

- **Level:** warning
- **Source:** phpstan-deprecation-rules

Drupal deprecates an interface method on the interface, and the implementation often says only
`{@inheritdoc}`, as `ConfigEntityBase::trustData()` does for `ConfigEntityInterface::trustData()`.
Mago marks a method deprecated only where its own docblock says so, so a call to the implementation
goes unreported. PHPStan counts the implementation as deprecated, and so does the check.

**Reports:**

- a call to a method that implements a deprecated interface method without saying so, an
  implementation from a trait included.

**Not reported:**

- a method whose docblock says `@not-deprecated`, as in PHPStan;
- a method declared by a parent class that doesn't implement the interface itself;
- an override of a deprecated class method. Mago checks every method call against every target, so
  only interface methods are targets, and Drupal deprecates through interfaces;
- a method flagged by Mago as deprecated itself, which is Mago's `deprecated-method`;
- a call in a [deprecation scope](#deprecation-scopes), and one that the
  [deprecation target](#deprecation-target) leaves for later.

??? example "Example"

    ```php title="modules/example/src/ThingInterface.php"
    namespace Drupal\example;

    /**
     * A thing.
     */
    interface ThingInterface {

      /**
       * Trusts the data of the thing.
       *
       * @deprecated in example:1.2.0 and is removed from example:2.0.0. Use
       *   trustAll() instead.
       *
       * @see https://www.drupal.org/node/3000000
       */
      public function trust(): void;

      /**
       * Trusts all data of the thing.
       */
      public function trustAll(): void;

    }
    ```

    ```php title="modules/example/src/Thing.php"
    namespace Drupal\example;

    /**
     * A thing.
     */
    class Thing implements ThingInterface {

      /**
       * {@inheritdoc}
       */
      public function trust(): void {}

      /**
       * {@inheritdoc}
       */
      public function trustAll(): void {}

    }
    ```

    ```php
    // Reported.
    use Drupal\example\Thing;

    /**
     * Trusts the thing.
     */
    function example_trust(Thing $thing): void {
      $thing->trust();
    }

    // Fine.
    use Drupal\example\Thing;

    /**
     * Trusts the thing.
     */
    function example_trust(Thing $thing): void {
      $thing->trustAll();
    }
    ```

??? note "Compared with phpstan-deprecation-rules"

    - PHPStan also counts the override of a deprecated class method as deprecated, unless it says
      `@not-deprecated`. The check follows interface methods only.
    - phpstan-deprecation-rules reads the deprecations of every package. The check reads only
      Drupal's files.

---

## drupal/implicit-transaction-commit

- **Level:** warning
- **Source:** Drupal's database API

Drupal commits a transaction when its object goes out of scope. Drupal 11.5 deprecates that, in
favor of `Transaction::commitOrRelease()`, which was added in Drupal 11.3.

**Reports:**

- a `startTransaction()` call whose result is thrown away, which commits at once;
- a transaction kept in a local variable that the function, method or closure never calls
  `commitOrRelease()` on. Unsetting or overwriting the variable commits it the same way.

**Not reported:**

- a transaction ended by a later standalone `rollBack()` in the same block, with no control flow
  between them. A rollback in a condition, or only in a `catch`, keeps the report;
- a variable that is returned, passed to a call, stored elsewhere or captured by a closure, since
  other code may commit it;
- a transaction stored on a property;
- any transaction while `Transaction::commitOrRelease()` doesn't exist in the codebase, as before
  Drupal 11.3;
- test code, which exercises the destructor on purpose, and hook documentation in `*.api.php`
  files.

???+ example "Example"

    ```php
    // Reported.
    use Drupal\Core\Database\Connection;

    /**
     * Saves the example data.
     */
    function example_save(Connection $database): void {
      $transaction = $database->startTransaction();
      example_write($database);
    }

    // Fine.
    use Drupal\Core\Database\Connection;

    /**
     * Saves the example data.
     */
    function example_save(Connection $database): void {
      $transaction = $database->startTransaction();
      example_write($database);
      $transaction->commitOrRelease();
    }
    ```

??? note "Compared with phpstan-drupal"

    phpstan-drupal doesn't check transactions.
