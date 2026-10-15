# Traits

Mago analyzes a trait on its own, without the classes that use it. The analyzer types the calls
from a trait to the methods of those classes, and the fluent methods kept in traits by core. The
check reports a `self::` or `static::` call from a static method of a trait to an instance method.

## Calls from traits

Mago analyzes a trait once, on its own. A call on `$this` to a method that the trait doesn't declare
is then a missing method, and returns `mixed`, even when every class that uses the trait has the
method. PHPStan checks the body of a trait in each class that uses it instead. The analyzer finds
the classes that use the trait, and when every one of them has the method, it types the call from
their declarations.

```php
trait RedirectTrait {
  protected function redirectUrl(): Url {
    // Typed from the getEntity() of the forms that use the trait.
    return $this->getEntity()->toUrl();
  }
}
```

**Typed:**

- the result, as the union of the return types of the classes. `static` and `$this` mean the class
  that uses the trait, so they stay the trait's `$this`, and a call chained on the result is typed
  the same way;
- a parameter, only when every class gives it the same type, so an argument is checked where the
  classes agree;
- `self::name()` and `static::name()` in an instance method, which reach an instance method through
  `$this`. In a static method, the call is
  [`drupal/trait-non-static-call`](#drupaltrait-non-static-call).

**Not typed:**

- a call to a method that one of the classes lacks, which keeps the report;
- a call in a trait that no class uses, or in one that doesn't declare a method of its own, since
  the classes are found through one of its methods;
- a call to a generic method, which returns `mixed`, and to one whose documented return type names
  something that can't be resolved by Mago, such as a stray `*` after the class name in a docblock.
  A call on the class returns `mixed` there too.

The search runs once per trait, and looks only at the classes that use the trait without inheriting
it from another class that uses it. A subclass has every method and property of its parent, and PHP
keeps the return type of an override within the one that it overrides, so those classes answer for
their subclasses too. A test trait that thousands of test classes use through a few base classes
costs a few method lookups.

### Properties

A property on the `$this` of a trait, such as `$this->container`, gets the report half of this.
When every class that uses the trait has the property, declared or through a `@property` tag, the
report of a missing property is dropped:

- a static read, `self::$name` or `static::$name`, needs a declared static property in every class,
  since PHP throws for an instance or a magic one;
- `$this->name` needs a property that isn't static.

The value stays `mixed`. The SDK types a property only as a magic one, and on a trait without
`__get()` Mago would report that in turn. Mago doesn't record a reference for the read either, so a
private property, or a protected one in a final class, that only the trait reads is still reported
as `unused-property`.

## Fluent setters

Core documents `@return $this` on an interface, and keeps the body in a trait with a bare
`{@inheritdoc}`. A trait doesn't have a parent to inherit from, so on a concrete class the call is
typed `mixed`, and the rest of the chain with it. The analyzer hands back the receiver for the 42
methods of 16 core traits whose bodies only ever `return $this`.

```php
AccessResult::allowed()->addCacheableDependency($entity)->andIf($other);  // AccessResultAllowed
$node->setPublished()->setOwnerId(1);                                     // Node
$form_state->setValue('a', 1)->setValue('b', 2);                          // FormState
```

A typed chain lets Mago check calls that stay unchecked on `mixed`, so it can show mistakes. For
example, `setReason()` is declared on the neutral and forbidden results, not on `AccessResult`, and
core calls it after an `isAllowed()` check that doesn't say anything about the type.

---

## drupal/trait-non-static-call

- **Level:** error
- **Source:** PHP

A static method doesn't have `$this`, so `self::name()` or `static::name()` in it can't reach an
instance method, and PHP throws an `Error`. Mago analyzes a trait on its own, and reports a static
call to an instance method only from outside the class (`invalid-static-method-access`).

**Reports:**

- a `self::name()` or `static::name()` call to an instance method, in a static method of a trait.
  The call counts when every class that uses the trait has the method and one of them declares it
  as not static. A call in a closure inside the static method counts too.

**Not reported:**

- a call to a method that the trait declares itself;
- the same call in a class, which nothing reports;
- a call in a trait that no class uses.

??? example "Example"

    ```php title="modules/example/src/ExampleLabel.php"
    namespace Drupal\example;

    /**
     * Labels things.
     */
    final class ExampleLabel {

      use LabelTrait;

      /**
       * Returns the label.
       */
      public function label(): string {
        return 'Example';
      }

      /**
       * Returns the default label.
       */
      public static function defaultLabel(): string {
        return 'Example';
      }

    }
    ```

    ```php title="modules/example/src/LabelTrait.php"
    // Reported.
    namespace Drupal\example;

    /**
     * Prints labels.
     */
    trait LabelTrait {

      /**
       * Prints the label.
       */
      public static function printLabel(): void {
        echo self::label();
      }

    }

    // Fine.
    namespace Drupal\example;

    /**
     * Prints labels.
     */
    trait LabelTrait {

      /**
       * Prints the label.
       */
      public static function printLabel(): void {
        echo self::defaultLabel();
      }

    }
    ```

??? note "Compared with phpstan-drupal"

    phpstan-drupal doesn't have a rule for this. PHPStan reports the call as `method.staticCall`,
    since it checks the body of a trait in each class that uses it.
