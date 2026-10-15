# Tests

The analyzer types PHPUnit assertions, mocks and Prophecy doubles. The checks report assertions
that always pass, doubles that a type or a docblock gets wrong, and mistakes in Drupal's test
classes. The `phpunit` plugin does the PHPUnit and Prophecy work and depends on nothing in Drupal.
The `drupal` plugin has the checks for Drupal's test classes.

## Emptiness assertions

PHPUnit 11 puts `@phpstan-assert` on `assertTrue()`, `assertFalse()`, `assertNull()`,
`assertNotNull()`, `assertInstanceOf()`, `assertSame()` and the `assertIs*()` family, and Mago reads
it, so those assertions narrow the value on their own. `assertNotEmpty()` and `assertEmpty()` have
no such tag, and Drupal's tests use those two a lot. A loaded entity then stays nullable for the
rest of the test, and every call on it is reported.

The `phpunit` plugin narrows the value for these two: after `assertNotEmpty()` it isn't empty, and
after `assertEmpty()` it is empty.

**Not narrowed:**

- a `Countable`, `Traversable` or plain `object` value after `assertEmpty()`. PHPUnit counts such an
  object, so an empty collection passes the assertion.

```php
$node = Node::load(1);
$this->assertNotEmpty($node);
$node->label();            // narrowed, no possible-method-access-on-null
```

## Mock unions

Drupal's older tests document a mock as `@var X|MockObject`, from before PHP had intersection types.
Mago checks each half of a union on its own, so `expects()` is missing on `X`, and the call comes
back as `mixed`, which loses the type of the rest of the chain. phpstan-phpunit reads the union as
`X&MockObject`. A hook for that doesn't exist in Mago, and a provider type on the property would
count as a magic property, so the `phpunit` plugin types the two methods on `X` instead.

**Typed**, as `InvocationMocker`:

- `expects()` with one argument, which is checked by Mago against `InvocationOrder`;
- `method()` with a string literal that names a method of the type.

The plugin answers only when the type doesn't declare the method, and it sees that one type, not the
union.

**Not typed:**

- PHPUnit's own types, so `expects()` on the `Stub` half of an `X|Stub` union or on a `MockBuilder`
  is still reported.

```php
/** @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject */
protected $moduleHandler;

$this->moduleHandler->expects($this->once())->method('invokeAll')->willReturn([]);  // no issues
```

Since the plugin never sees the union, it also answers when the type is `X` alone.
[`phpunit/mock-call-on-plain-type`](#phpunitmock-call-on-plain-type) reports those calls.

A union mock passed where the mocked type goes is accepted too. Mago checks each member of the union
against the parameter and reports the `MockObject` half as `possibly-invalid-argument`. The plugin
drops that report when every other member of the union is the expected type or a class that fits
it. A `null` member is left to `possibly-null-argument`, and any other member keeps the report.
Core 11.4's `UnitTestCase::getStringTranslationStub()` returns such a union too.

```php
$this->worker->setStringTranslation($this->getStringTranslationStub());  // no issues
```

The other direction stays reported. Calling `invokeAll()` on the union still reports it missing on
`MockObject`, once per call and with the type of the call intact, since the plugin never sees `X`
from that side. A typed chain also lets Mago check calls that stay unchecked on `mixed`, such as
`with()` after `method()` on a stub, which PHPUnit's `InvocationStubber` doesn't have.

## Prophecy calls

`$prophecy->getFoo()` on an `ObjectProphecy<Foo>` goes through `__call()`, which returns a
`MethodProphecy`. Mago reports every such call as undocumented, since the class doesn't have a
`@method` tag for it. The `phpunit` plugin types the call as a `MethodProphecy` when the prophesized
class declares the method, or when the class isn't known. A method that the class lacks stays
reported, since Prophecy throws for it.

```php
$storage = $this->prophesize(EntityStorageInterface::class);
$storage->load(1)->willReturn($node);  // MethodProphecy, no non-documented-method
$storage->notAMethod();                // still reported
```

A prophecy documented as a union with its class, such as `@var Foo|ObjectProphecy`, is typed half by
half. The bare `ObjectProphecy` doesn't name a class, so its half gets a `MethodProphecy`. A
`ProphecyInterface` half doesn't have such a method at all. The `Foo` half gets the result of the
real method, although a prophecy is never a `Foo`.
[`phpunit/prophecy-union`](#phpunitprophecy-union) reports the docblock.

`willImplement()` and `willExtend()` declare `@phpstan-this-out static<T&U>`, which isn't applied by
Mago ([mago#2395](https://github.com/carthage-software/mago/issues/2395)). The plugin adds the type
that they name to the prophecy: through the return type for a chain, and by narrowing the variable
for a call on one. A prophecy told to implement an interface inside an `if` is a union afterwards,
and a later call adds its type to each member.

```php
$block = $this->prophesize(BlockPluginInterface::class);
$block->willImplement(PreviewFallbackInterface::class);
$block->getPreviewFallbackString()->willReturn('Placeholder');  // found on the added interface
```

Prophecy makes a double of an interface that is `Traversable`, but neither an `Iterator` nor an
`IteratorAggregate`, an `Iterator`. The plugin accepts `current()`, `key()`, `next()`, `rewind()`
and `valid()` on such a prophecy.

```php
$items = $this->prophesize(FieldItemListInterface::class);
$items->valid()->willReturn(TRUE, FALSE);  // added by Prophecy, no non-documented-method
```

---

## phpunit/redundant-assertion

- **Level:** warning
- **Source:** phpstan-phpunit

phpstan-phpunit reports a PHPUnit assertion that always passes, given the type of the value. Such an
assertion doesn't test anything.

**Reports:**

- an assertion that the type of its value already guarantees, such as `assertTrue(TRUE)`, or
  `assertInstanceOf()` on a value already typed as that class.

The check covers `assertInstanceOf()`, `assertNotNull()`, `assertNull()`, `assertTrue()`,
`assertFalse()`, `assertNotTrue()`, `assertNotFalse()`, and `assertIsArray()`, `assertIsBool()`,
`assertIsFloat()`, `assertIsInt()`, `assertIsNumeric()`, `assertIsObject()`, `assertIsScalar()`
and `assertIsString()`. A type from a docblock counts, and so does one supplied by the plugins, such
as the class of a service from a services file.

**Not reported:**

Mago types some values more narrowly than the code behaves, and these are left alone:

- `$this->name`, unless the native type of the property passes the assertion too. Mago keeps the
  narrowed type of a property across calls that may change it, such as a callback that sets
  `$this->fired`. A property typed only by `@var` never counts: PHP doesn't check the tag, and the
  property is NULL until something is assigned. A magic property, which doesn't have a declaration,
  never counts either;
- another object's property, `$other->name`, since the check doesn't know the class of that object;
- a variable that an inline `@var` tag names in the same method. The tag is the test's own claim,
  often over a `load()` that can return NULL, and the assertion is what checks it at runtime;
- a variable that a `static` or `global` statement in the file declares. Its value can come from an
  earlier call or change elsewhere, which isn't followed by Mago;
- `$items[0]`, since Mago's `ArrayAccess` stub types `offsetGet()` without null, while an empty
  field item list returns null;
- `isset()`, which tests `__isset()` or `ArrayAccess` rather than a type;
- any of these behind a `!`;
- an assertion that can never pass. When the value is a variable, Mago reports it as
  `impossible-type-comparison`, and otherwise it isn't reported.

??? example "Example"

    ```php title="modules/example/tests/src/Unit/ExampleTest.php"
    // Reported.
    namespace Drupal\Tests\example\Unit;

    use PHPUnit\Framework\TestCase;

    final class ExampleTest extends TestCase {

      public function testNames(): void {
        $names = $this->names();
        $this->assertIsArray($names);
      }

      /**
       * @return list<string>
       */
      private function names(): array {
        return ['a', 'b'];
      }

    }

    // Fine.
    namespace Drupal\Tests\example\Unit;

    use PHPUnit\Framework\TestCase;

    final class ExampleTest extends TestCase {

      public function testNames(): void {
        $names = $this->names();
        $this->assertNotEmpty($names);
      }

      /**
       * @return list<string>
       */
      private function names(): array {
        return ['a', 'b'];
      }

    }
    ```

??? note "Compared with phpstan-phpunit"

    - phpstan-phpunit makes PHPStan read more assertions, such as `assertSame()`, `assertCount()`,
      `assertEmpty()` and `assertArrayHasKey()`, and their `Not` forms, so PHPStan reports those
      when they always pass too.
    - PHPStan also reports an assertion that can never pass, whatever the value is.
    - PHPStan forgets the narrowed type of a property after any call that may change it. Mago
      forgets it only after a method call on `$this`, such as `$this->change()`. It keeps it after a
      function call, a static call and a call on another object, such as a dispatcher that runs a
      callback which sets the property. So the check asks for the native type of the property
      instead.

---

## phpunit/mock-call-on-plain-type

- **Level:** warning
- **Source:** PHPStan

A mock typed as the mocked class alone, without `MockObject`, doesn't have `expects()` or `method()`
for Mago. The [mock union](#mock-unions) types answer for such a call all the same, since they see
one type at a time. This check sees the whole type of the value and reports the call: the value is a
mock at runtime, but the property, parameter or return type that it comes from leaves `MockObject`
out. PHPStan reports the same calls as undefined methods.

**Reports:**

- an `expects()` or `method()` call on a value whose type doesn't name a PHPUnit class or declare
  the method. The check takes an `expects()` call with one argument, and a `method()` call that
  names a method of the type.

**Not reported:**

- a value whose type includes a PHPUnit type, such as `X|MockObject` or `X&MockObject`;
- any other call, which stays Mago's own report of a missing method.

??? example "Example"

    ```php title="modules/example/src/GreeterInterface.php"
    namespace Drupal\example;

    interface GreeterInterface {

      public function greet(): string;

    }
    ```

    ```php title="modules/example/tests/src/Unit/GreeterTest.php"
    // Reported.
    namespace Drupal\Tests\example\Unit;

    use Drupal\example\GreeterInterface;
    use PHPUnit\Framework\TestCase;

    final class GreeterTest extends TestCase {

      /**
       * @var \Drupal\example\GreeterInterface
       */
      protected $greeter;

      protected function setUp(): void {
        $this->greeter = $this->createMock(GreeterInterface::class);
      }

      public function testGreet(): void {
        $this->greeter->expects($this->once())->method('greet');
      }

    }

    // Fine.
    namespace Drupal\Tests\example\Unit;

    use Drupal\example\GreeterInterface;
    use PHPUnit\Framework\TestCase;

    final class GreeterTest extends TestCase {

      /**
       * @var \Drupal\example\GreeterInterface&\PHPUnit\Framework\MockObject\MockObject
       */
      protected $greeter;

      protected function setUp(): void {
        $this->greeter = $this->createMock(GreeterInterface::class);
      }

      public function testGreet(): void {
        $this->greeter->expects($this->once())->method('greet');
      }

    }
    ```

??? note "Compared with PHPStan"

    PHPStan reports these calls as calls to undefined methods, as it reports any other `MockObject`
    method called on such a value. The check reports only `expects()` and `method()`, and Mago
    reports the other calls itself.

---

## phpunit/prophecy-union

- **Level:** warning
- **Source:** Prophecy's own types

A prophecy is never an instance of the class that it prophesizes, so a type that unions the two is
wrong. Calls on such a value, and the calls chained on them, are reported on one half or the other,
far from the docblock that causes them. The check reports the docblock once, with the type to use
instead, `ObjectProphecy<Foo>`.

**Reports:**

- a docblock type that unions `ProphecyInterface` or `ObjectProphecy` with another class. The check
  reads the `@var`, `@param` and `@return` tags of properties, parameters and return types in a
  class or a trait.

??? example "Example"

    ```php title="modules/example/tests/src/Unit/ExampleTest.php"
    // Reported.
    namespace Drupal\Tests\example\Unit;

    use PHPUnit\Framework\TestCase;

    final class ExampleTest extends TestCase {

      /**
       * @var \Drupal\Core\Extension\ModuleHandlerInterface|\Prophecy\Prophecy\ProphecyInterface
       */
      protected $moduleHandler;

    }

    // Fine.
    namespace Drupal\Tests\example\Unit;

    use PHPUnit\Framework\TestCase;

    final class ExampleTest extends TestCase {

      /**
       * @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\Core\Extension\ModuleHandlerInterface>
       */
      protected $moduleHandler;

    }
    ```

??? note "Compared with phpstan-drupal and phpstan-phpunit"

    Neither has a check for these docblocks.

---

## drupal/test-modules-visibility

- **Level:** error
- **Source:** phpstan-drupal
- **Ports:** phpstan-drupal's `TestClassesProtectedPropertyModulesRule`

phpstan-drupal reports a test class whose `$modules` property is public. Drupal's test bases declare
it protected, see the [change record](https://www.drupal.org/node/2909426).

**Reports:**

- a public `$modules` property, static or not, typed or not, declared in a class that descends from
  PHPUnit's `TestCase`.

**Not reported:**

- a `$modules` that the class inherits from a parent or gets from a trait. The class that declares
  it is the one to fix.

??? note "Compared with phpstan-drupal"

    phpstan-drupal also reports a `$modules` declared with `var`, which PHP makes public too, and a
    public `$modules` declared by a trait, once for each test class that uses the trait.

---

## drupal/browser-test-default-theme

- **Level:** error
- **Source:** phpstan-drupal
- **Ports:** phpstan-drupal's `BrowserTestBaseDefaultThemeRule`

phpstan-drupal reports a functional test that doesn't set `$defaultTheme`. When the install profile
of the test doesn't install a theme, Drupal throws while it sets up the test.

**Reports:**

- a browser test on a themeless profile that doesn't set `$defaultTheme`. The test is a concrete
  `BrowserTestBase` descendant whose name ends in `Test`, and the property counts when it is set on
  the class, on a base class, or by a trait that either of them uses.

The check finds the test's `$profile` the same way, on the class, a base class or a trait. A profile
is themeless when it doesn't ship a `system.theme` config in `config/sync` or `config/install`,
which is checked by Drupal when the test runs. Profiles are read from core, from the site's
`profiles` directory, and from the `tests/profiles` directories of core's modules and of extensions.
A profile not found there counts as themeless when it is one of core's themeless test profiles, such
as `testing`.

**Not reported:**

- a class that overrides `installDefaultThemeFromClassProperty()`, itself, through a base class or
  through a trait, since the override may set the theme at runtime;
- a class that assigns `$this->defaultTheme` itself, such as in `setUp()`;
- update path tests, which install from a database dump;
- a test on a `NULL` or `FALSE` profile, which installs from existing configuration and takes its
  theme;
- a profile that isn't a string literal, such as one read from a constant.

??? example "Example"

    ```php title="modules/example/tests/src/Functional/ExampleTest.php"
    // Reported.
    namespace Drupal\Tests\example\Functional;

    use Drupal\Tests\BrowserTestBase;

    final class ExampleTest extends BrowserTestBase {

      public function testPage(): void {}

    }

    // Fine.
    namespace Drupal\Tests\example\Functional;

    use Drupal\Tests\BrowserTestBase;

    final class ExampleTest extends BrowserTestBase {

      /**
       * {@inheritdoc}
       */
      protected $defaultTheme = 'stark';

      public function testPage(): void {}

    }
    ```

??? note "Compared with phpstan-drupal"

    - phpstan-drupal checks a class only in a `Functional` or `FunctionalJavascript` namespace, as
      the fourth part of a module test's namespace, or in core's `FunctionalTests` and
      `FunctionalJavascriptTests`. The check covers every `BrowserTestBase` descendant.
    - phpstan-drupal counts only core's seven themeless test profiles as themeless, and leaves a
      test on any other profile alone. The check reads the config of each profile from disk.
    - phpstan-drupal skips the two installer test bases that install from existing configuration by
      class name. The check skips a test on a `NULL` profile, which is what those bases set, so it
      also skips other tests on a `NULL` profile, which are reported by phpstan-drupal.
    - phpstan-drupal reports a class that sets the theme in `setUp()` or that overrides
      `installDefaultThemeFromClassProperty()`. The check leaves both alone.

---

## drupal/component-test-core-base

- **Level:** error
- **Source:** Drupal core's PHPStan rules
- **Ports:** core's `ComponentTestDoesNotExtendCoreTest` rule

Core's component tests run without Drupal, so they can't extend a test base that boots Drupal or
needs its classes. Core's own PHPStan rules report a component test that does.

**Reports:**

- a class in the `Drupal\Tests\Component` namespace that extends `UnitTestCase`, `KernelTestBase`,
  `BuildTestBase` or `BrowserTestBase`, directly or through its parents.

`WebDriverTestBase` counts, since it extends `BrowserTestBase`. The report is on the class whose own
parent lies outside the component tests. So a component test base that extends a core one is
reported once, and not again on the tests below it.

??? note "Compared with core's rule"

    Core's rule reports every component test below such a base too, not only the base.

---

## drupal/test-class-name

- **Level:** error
- **Source:** phpstan-drupal
- **Ports:** phpstan-drupal's `TestClassSuffixNameRule`, the part that needs the class's ancestry

PHPUnit runs only the files named `*Test.php`, and so do `phpunit -c core` and run-tests.sh, so a
test class whose name doesn't end in `Test` never runs. The lint rule
[`drupal/test-class-suffix`](../rules/naming-and-imports.md#drupaltest-class-suffix) reports most of
them from the parent's name, with no types. The check reports the ones that the lint rule can't
tell apart from other classes.

**Reports:**

- a concrete class that descends from PHPUnit's `TestCase`, whose name doesn't end in `Test`, and
  that `drupal/test-class-suffix` doesn't take for a test. That is a class outside the lint rule's
  test directories, such as one directly in `tests/src` or in `src`, a class whose parent's name
  doesn't end in `Test`, `TestBase` or `TestCase`, and a class whose parent is declared in the same
  file without such a parent of its own.

**Not reported:**

- a class that the lint rule reports, so a class gets one report when both run;
- an abstract class and an anonymous class.

??? example "Example"

    ```php title="modules/example/tests/src/Unit/ExampleUnitBase.php"
    namespace Drupal\Tests\example\Unit;

    use Drupal\Tests\UnitTestCase;

    abstract class ExampleUnitBase extends UnitTestCase {
    }
    ```

    ```php title="modules/example/tests/src/Unit/ExampleChecks.php"
    // Reported.
    namespace Drupal\Tests\example\Unit;

    final class ExampleChecks extends ExampleUnitBase {
    }

    // Fine.
    // The class and its file are named ExampleChecksTest.
    namespace Drupal\Tests\example\Unit;

    final class ExampleChecksTest extends ExampleUnitBase {
    }
    ```

??? note "Compared with phpstan-drupal"

    phpstan-drupal reports every such class from one rule. The check leaves the classes that the
    lint rule reports to it, so a project that runs `mago analyze` without `mago lint` doesn't see
    those.
