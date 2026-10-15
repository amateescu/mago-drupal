# Naming and imports

Rules for the names of classes, constants, functions, methods and properties, and for `use`
statements. Mago's own naming rules cover the rest, see
[Configuration](../setup/configuration.md).

---

## drupal/class-name-acronym

- **Level:** error
- **Fix:** none
- **Standard:** [Classes, methods and properties](https://project.pages.drupalcode.org/coding_standards/php/coding/#classes-methods-and-properties)
- **Ports:** `Drupal.NamingConventions.ValidClassName.NoUpperAcronyms`

> If an acronym is used in a class or method name, make it CamelCase too (SampleXmlClass, not
> SampleXMLClass).

**Reports:**

- a class, interface, trait or enum name that starts with three upper-case letters and has no
  lower-case letter after them, such as `HTTP` or `URL_2`. Digits, underscores and multi-byte
  letters don't count as lower case.

**Not reported:**

- a name with a lower-case letter after the capitals, such as `HTTPClient`. The rule follows Coder
  here, although the standard asks for `HttpClient`.

??? note "Compared with Coder"

    - The rule reports on the name. Coder 9 reports on the `class` keyword, so the lines differ when
      a declaration spans several lines.

---

## drupal/define-name

- **Level:** error
- **Fix:** none
- **Standard:** [Constants](https://project.pages.drupalcode.org/coding_standards/php/coding/#constants)
- **Ports:** `Generic.NamingConventions.UpperCaseConstantName.ConstantNotUpperCase`

> Constants should always be all-uppercase, with underscores to separate words.

**Reports:**

- a `define()` constant name that isn't upper case. The rule reads the first argument, a string
  literal or literals joined with `.`, and checks the part after the last backslash. Only ASCII
  letters count. It reports in every scanned file type and at any depth. It matches `define` in any
  letter case, with or without a leading backslash, as Coder does.

**Not reported:**

- a name that has a variable in it, such as `'mymod_' . $x`.

Mago's `constant-name` covers `const`, the other half of the sniff.

??? note "Compared with Coder"

    - A name built from literals joined with `.` is checked as a whole. Coder 9 checks only the
      first literal.
    - Coder 9 reports a name that has a variable in it, such as `'mymod_' . $x`.
    - The rule also reports a `define()` call inside an attribute argument, and `namespace\define()`
      in a file with no namespace declaration. Coder 9 reports neither.

---

## drupal/enum-case-name

- **Level:** error
- **Fix:** none
- **Standard:** [Enums](https://project.pages.drupalcode.org/coding_standards/php/coding/#enums)
- **Ports:** `Drupal.NamingConventions.ValidEnumCase`: `NoUnderscores`, `NoUpperAcronyms`, `StartWithCapital`

> Enums follow the same conventions as
> [classes](https://project.pages.drupalcode.org/coding_standards/php/documentation/#classes-and-namespaces)
> with the addition that for their cases the enums must use UpperCamelCase.

**Reports:**

- an enum case name that doesn't start with a capital letter;
- an enum case name that holds an underscore;
- an enum case name that starts with three upper-case letters and has no lower-case letter after
  them, the check of `drupal/class-name-acronym`.

A name that fails several checks gets one issue for each.

??? example "Example"

    ```php
    // Reported, on each case.
    enum Status {
      case pending;
      case Not_Found;
      case HTTP;
    }

    // Fine.
    enum Status {
      case Pending;
      case NotFound;
      case Http;
    }
    ```

---

## drupal/fully-qualified-name

- **Level:** error
- **Fix:** safe. It adds the import and writes the short name everywhere the file writes the class
  in full.
- **Standard:** ["use"-ing classes](https://project.pages.drupalcode.org/coding_standards/php/namespaces/#use-ing-classes)
- **Ports:** `Drupal.Classes.FullyQualifiedNamespace.UseStatementMissing`

> Classes and interfaces with a backslash `\` inside their fully-qualified name (for example:
> `Drupal\Tests\BrowserTestBase`) must not use their fully-qualified name inside the code. If the
> namespace differs from the namespace of the current file, put a `use` statement on the top of the
> file.

**Reports:**

- a namespaced class written out in full where a `use` statement belongs.

**Not reported:**

- a name with no namespace of its own, such as `\Exception`;
- a namespaced function call or first-class callable;
- an `.api.php` file.

In a file with no namespace, the fix puts the import below the `@file` docblock and any `declare`,
or below the open tag, and after the last `use` when the file has one. There is no fix in a file
with several namespaces or a braced one, where the import would go below code or a constant, and
when the file already uses the short name for something else: another import, a class of that
name, or a docblock that writes the short name in the same case.

??? example "Example"

    The fix turns the reported code into the fine code.

    ```php
    // Reported.
    function example_load(int $id): ?object {
      return \Drupal\node\Entity\Node::load($id);
    }

    // Fine.
    use Drupal\node\Entity\Node;

    function example_load(int $id): ?object {
      return Node::load($id);
    }
    ```

??? note "Compared with Coder"

    - Coder reports a namespaced function call. Its fix imports the function name as a class, and
      the call then reaches the global function.
    - The rule skips the names in a `use` group. Coder reports the member, and its fix changes the
      statement.
    - A name in the conflict block of a trait `use` isn't reported. Coder reports it.
    - In a file with no namespace, the fix never puts an import in the clause of a closure. Coder's
      fix does when a closure holds the first `use` of the file.
    - There is no fix when the short name is taken by an interface, a trait, an enum, an import that
      differs in case only, or a class whose name has a comment before it. Coder reports a fixable
      error there, and its fix writes code that PHP rejects.
    - The rule also reports a namespace-relative name such as `namespace\Sub\Bar`. Coder never
      checks that form.

---

## drupal/function-name

- **Level:** error
- **Fix:** none
- **Standard:** [Functions and variables](https://project.pages.drupalcode.org/coding_standards/php/coding/#functions-and-variables)
- **Ports:** `Drupal.NamingConventions.ValidFunctionName.InvalidName`
- **Off with `--core`**

> Functions should be named using lowercase, and words should be separated with an underscore.

**Reports:**

- a function whose name isn't lower case, such as `mymoduleHelper()` or `mymodule_Helper()`. The
  rule checks every function outside a class, interface, trait or enum, in every file.

**Not reported:**

- a leading or doubled underscore, so a private helper such as `_mymodule_helper()` passes;
- a function that starts with `hook_` in an `.api.php` file, since such a file documents hooks
  with names such as `hook_ENTITY_TYPE_insert()`;
- methods, which are left to Mago's `method-name`.

Mago's own `function-name` wants snake case, so it reports `_mymodule_helper()`, which is accepted
by Coder. The [configuration](../setup/configuration.md) turns it off.

---

## drupal/global-class-backslash

- **Level:** error
- **Fix:** safe. It adds the backslash.
- **Standard:** ["use"-ing classes](https://project.pages.drupalcode.org/coding_standards/php/namespaces/#use-ing-classes)
- **Ports:** none. No such sniff exists in Coder.
- **Off with `--core`**

> Classes and interfaces without a backslash `\` inside their fully-qualified name (for example, the
> built-in PHP Exception class) must be fully qualified. For example: `new \Exception();`.

**Reports:**

- a class name without a leading backslash in a file with no `namespace`, such as `new DateTime()`
  or `Drupal::service()` in a `.module` file. The rule reads class names in types, `extends`,
  `implements`, trait `use`, attributes, `new`, static calls and accesses, `::class`, and on the
  right of `instanceof`.

**Not reported:**

- a name that a `use` statement imports;
- `self`, `static`, `parent` and the built-in types, which aren't class names;
- an `.api.php` file. Its examples leave out the imports, so a short name there, such as `Url`,
  stands for a namespaced class.

In a file with no namespace both forms name the same class, so the fix doesn't change what the code
does.

Mago's `no-fully-qualified-global-class-like`, off by default, asks for the opposite.

---

## drupal/hook-attribute-name

- **Level:** warning
- **Fix:** none
- **Source:** Coder's `Drupal` standard
- **Ports:** `Drupal.Attributes.ValidHookName.HookPrefix`
- **Off with `--core`**

Coder asks for the hook name in a `#[Hook]` attribute without the `hook_` prefix. The attribute
holds the hook name without the prefix, so a name with it is never invoked.

**Reports:**

- a `Drupal\Core\Hook\Attribute\Hook` attribute whose first argument, positional or `hook:`, is a
  hook name that starts with `hook_`. The rule resolves the attribute name, so an alias or a fully
  qualified name counts, and a bare `Hook` that isn't imported from Drupal's class doesn't. Every
  attribute of a group is checked. A hook name built from literals joined with `.` is checked as a
  whole.

??? example "Example"

    ```php title="src/Hook/ExampleHooks.php"
    // Reported.
    namespace Drupal\example\Hook;

    use Drupal\Core\Hook\Attribute\Hook;

    class ExampleHooks {

      #[Hook('hook_cron')]
      public function cron(): void {
      }

    }

    // Fine.
    namespace Drupal\example\Hook;

    use Drupal\Core\Hook\Attribute\Hook;

    class ExampleHooks {

      #[Hook('cron')]
      public function cron(): void {
      }

    }
    ```

??? note "Compared with Coder"

    - Coder's fix removes the prefix. The rule has no fix, because removing the prefix changes which
      hook runs.
    - It reads only the attribute's own first argument. Coder 9 reads the next string literal in the
      file, so it also reports an unrelated string below a `#[Hook(self::CRON)]` or a `#[Hook]`
      attribute.

---

## drupal/method-name-underscore

- **Level:** warning
- **Fix:** none
- **Standard:** [Classes, methods and properties](https://project.pages.drupalcode.org/coding_standards/php/coding/#classes-methods-and-properties),
  [Methods and functions](https://project.pages.drupalcode.org/coding_standards/php/coding/#methods-and-functions)
- **Ports:**
    - `Drupal.NamingConventions.ValidFunctionName.MethodDoubleUnderscore`
    - `PSR2.Methods.MethodDeclaration.Underscore`
- **Off with `--core`**

> Method names must not be prefixed with a single underscore to indicate protected or private
> visibility. That is, an underscore prefix explicitly has no meaning.

**Reports:**

- a method name that starts with one underscore to mark it private;
- a method name that starts with two underscores and isn't a PHP magic method or a `SoapClient`
  method.

**Not reported:**

- a name that is only two underscores, or starts with three.

The names are matched without regard to case. The two-underscore check accepts 29 names: the 17
magic methods of PHP and the 12 methods of `SoapClient`. Mago's `method-name` rule reports no name
that starts with two underscores, so no other rule repeats this check.

??? note "Compared with Coder"

    - The rule reports on the name. Coder 9 reports on the `function` keyword, so the lines differ
      when a declaration spans several lines.

---

## drupal/property-name

- **Level:** error
- **Fix:** none
- **Standard:** [Classes, methods and properties](https://project.pages.drupalcode.org/coding_standards/php/coding/#classes-methods-and-properties)
- **Ports:**
    - `Drupal.NamingConventions.ValidVariableName.LowerCamelName`
    - `PSR2.Classes.PropertyDeclaration.Underscore`

> Methods and class properties should use lowerCamel naming. In Drupal 8, properties of
> configuration entities are exempt of these conventions. Those properties are allowed to use
> underscores.

**Reports:**

- a class property whose name doesn't start with a lower-case letter or holds an underscore.

**Not reported:**

- local variables;
- a property of a config entity or a plugin annotation, which may use any case: a class whose
  parent name contains `ConfigEntity` or is `Plugin` or `ViewsPluginAnnotationBase`, and a class
  that implements `AnnotationInterface`. A name that starts with an underscore is still reported in
  these classes.

The names are compared as written, so `\Drupal\Core\Config\Entity\ConfigEntityBase` counts, while
an alias of `Plugin` or `\Drupal\Component\Annotation\Plugin` doesn't. Only the first parent of an
interface counts. The rule reads the outermost class around the property, and only when that class
is at the top level of the file. An anonymous class in a method follows the class around it, and a
class declared inside an `if` or a function is checked.

??? note "Compared with Coder"

    - Core's config turns `PSR2.Classes.PropertyDeclaration.Underscore` off. The rule still reports
      a leading underscore under `--core`. The lowerCamelCase check covers it in most classes, but
      not in the config entities and plugin annotations above, where Coder reports nothing under
      core's config.

---

## drupal/redundant-use

- **Level:** error
- **Fix:** safe. It removes the import and writes the name with a leading backslash, such as
  `\Exception`, at every reference.
- **Standard:** ["use"-ing classes](https://project.pages.drupalcode.org/coding_standards/php/namespaces/#use-ing-classes)
- **Ports:** `Drupal.Classes.UseGlobalClass.RedundantUseStatement`

> Classes and interfaces without a backslash `\` inside their fully-qualified name (for example, the
> built-in PHP Exception class) must be fully qualified. For example: `new \Exception();`. Do not
> `use` global classes.

**Reports:**

- a `use` statement that imports a class from the global namespace, also one with a leading
  backslash such as `use \DateTime;`.

**Not reported:**

- `use const`;
- grouped imports such as `use Foo\{Bar, Baz};`.

The fix reads the references from the resolved names. There is no fix while a docblock in the file
names the class in the same case without a leading backslash, in a type, an annotation or prose.
`drupal/doc-type-namespace` fixes the types.

??? example "Example"

    The fix turns the reported code into the fine code.

    ```php title="src/Example.php"
    // Reported.
    namespace Drupal\example;

    use Exception;

    class Example {

      public function fail(): void {
        throw new Exception('Failed.');
      }

    }

    // Fine.
    namespace Drupal\example;

    class Example {

      public function fail(): void {
        throw new \Exception('Failed.');
      }

    }
    ```

??? note "Compared with Coder"

    - Coder reports `use const` and grouped imports, and phpcbf breaks the code there: it writes
      `\const` for a constant import and the group prefix for a grouped name.
    - Coder's sniff skips an import with a leading backslash, such as `use \DateTime;`. Coder
      reports that line only for the backslash, which is covered by `drupal/use-leading-backslash`.

---

## drupal/test-class-suffix

- **Level:** error
- **Fix:** none
- **Standard:** [Classes, methods and properties](https://project.pages.drupalcode.org/coding_standards/php/coding/#classes-methods-and-properties)
- **Ports:** phpstan-drupal's `TestClassSuffixNameRule` (partly)

> Test classes should always have the suffix "Test".

**Reports:**

- a concrete class in a PHPUnit test directory whose parent's name ends in `Test`, `TestBase` or
  `TestCase`, and whose own name doesn't end in `Test`. PHPUnit runs only the files named
  `*Test.php`, and so do `phpunit -c core` and run-tests.sh, so such a test never runs.

**Not reported:**

- a class outside the test directories below, where the parent's name says nothing: core's test
  entity class is called `EntityTest`.

The test directories are `Unit`, `Kernel`, `Functional`, `FunctionalJavascript` and `Build` under a
module's `tests/src`, and `Tests`, `KernelTests`, `FunctionalTests`, `FunctionalJavascriptTests` and
`BuildTests` under `core/tests/Drupal`.

??? example "Example"

    ```php title="tests/src/Unit/ExampleChecks.php"
    // Reported.
    namespace Drupal\Tests\example\Unit;

    use Drupal\Tests\UnitTestCase;

    final class ExampleChecks extends UnitTestCase {
    }

    // Fine.
    // The class and its file are named ExampleChecksTest.
    namespace Drupal\Tests\example\Unit;

    use Drupal\Tests\UnitTestCase;

    final class ExampleChecksTest extends UnitTestCase {
    }
    ```

??? note "Compared with phpstan-drupal"

    - phpstan-drupal reports any concrete subclass of PHPUnit's `TestCase` whose name doesn't end
      in `Test`, in any directory and whatever its parent is called.

---

## drupal/use-leading-backslash

- **Level:** error
- **Fix:** safe. It removes the backslash.
- **Standard:** ["use"-ing classes](https://project.pages.drupalcode.org/coding_standards/php/namespaces/#use-ing-classes)
- **Ports:** `SlevomatCodingStandard.Namespaces.UseDoesNotStartWithBackslash.UseStartsWithBackslash`

> When importing a class with "use", do not include a leading `\`.

**Reports:**

- an import whose first name starts with a backslash, as in `use \Drupal\node\Entity\Node;`. The
  rule reads the name after `use`, `use function` or `use const`, in any letter case, or the prefix
  of a group, and a comment before the name doesn't hide it.

**Not reported:**

- a later name of the statement, so `use A, \B;` isn't reported. `mago format` writes each name on
  its own `use` statement, and then every name is checked.

A `use` statement always names a class, function or constant from the root, so the backslash adds
nothing.

??? note "Compared with Coder"

    - Coder reads `function` and `const` only in lower case, so it doesn't report
      `use FUNCTION \foo;`. The rule reads the keyword in any case, as PHP does.
