# Using the right API

Rules for calls that have a Drupal API in their place, and for the strings that Drupal's tools read
from the source.

---

## drupal/deprecation-message

- **Level:** warning
- **Fix:** none
- **Source:** Drupal's [How to deprecate](https://www.drupal.org/about/core/policies/core-change-policies/how-to-deprecate#how-trigger-error) page
- **Ports:** `Drupal.Semantics.FunctionTriggerError`: `TriggerErrorPeriodAfterSeeUrl`, `TriggerErrorSeeUrlFormat`, `TriggerErrorTextLayoutRelaxed`, `TriggerErrorTextLayoutStrict`, `TriggerErrorVersion`

Drupal's How to deprecate page sets the wording of a deprecation message. Drupal's tools parse
these messages, so the wording must match:

- **Strict**, when the function, method or file has a `@deprecated` tag:
  `%thing% is deprecated in %deprecation-version% and is removed from %removal-version%. %extra-info%. See %cr-link%`
- **Relaxed**, for any other notice:
  `%thing% is deprecated in %deprecation-version% any free text %removal-version%. %extra-info%. See %cr-link%`

A version is `drupal:n.n.n`, `project:n.x-n.n` or `project:n.n.n`, with an optional release label
such as `-beta1`. The change-record link is a `drupal.org/node`, a `drupal.org/project` issue or a
`git.drupalcode.org` work item, with no punctuation after it.

**Reports:**

- an `E_USER_DEPRECATED` message of `trigger_error()` that breaks that wording.

**Not reported:**

- a message that starts with a variable, such as `$name . ' is deprecated in ...'` or
  `$this->message`.

The rule checks the text that the message is built from. A `sprintf()` call gives its format
string. Otherwise an interpolated string keeps its variables, and a part that is not a string, such
as `__CLASS__` or `static::class`, keeps its source text. So `__CLASS__ . ' is deprecated in ...'`
and `"$name is deprecated in ..."` have a `%thing%`.

For a notice at the top of a file, the rule reads the next docblock at file level. For a notice in
a function or method, it reads the docblock of that declaration, above or below its attributes. A
`@deprecated` tag at the start of a docblock line counts in any letter case. A mention of the tag
inside the text does not.

???+ example "Example"

    ```php
    // Reported.
    /**
     * Builds the list.
     *
     * @deprecated in example:1.2.0 and is removed from example:2.0.0. Use
     *   example_build() instead.
     *
     * @see https://www.drupal.org/node/3000000
     */
    function example_old_build(): array {
      @trigger_error('example_old_build() is deprecated. Use example_build() instead.', E_USER_DEPRECATED);
      return example_build();
    }

    // Fine.
    /**
     * Builds the list.
     *
     * @deprecated in example:1.2.0 and is removed from example:2.0.0. Use
     *   example_build() instead.
     *
     * @see https://www.drupal.org/node/3000000
     */
    function example_old_build(): array {
      @trigger_error('example_old_build() is deprecated in example:1.2.0 and is removed from example:2.0.0. Use example_build() instead. See https://www.drupal.org/node/3000000', E_USER_DEPRECATED);
      return example_build();
    }
    ```

??? note "Compared with Coder"

    - Coder joins the pieces of a message with spaces. It reports a valid message that a split
      leaves with two spaces, as in `'... See ' . 'https://...'`, a heredoc message, and a
      `\sprintf()` call. The rule reports none of these.
    - Coder takes the nearest docblock before the call when it sits one nesting level above the
      call. A method with no docblock then takes the tag of the method above it, and a closure in a
      tagged method gets the relaxed wording. The rule uses the docblock of the enclosing function
      or method, so a method with no docblock gets the relaxed wording, and a closure gets the
      wording of the method around it.
    - The rule also checks the message of `TRIGGER_ERROR()` and any other spelling in another letter
      case. PHP calls the same function, but Coder matches the name exactly and skips them.

---

## drupal/global-function

- **Level:** warning
- **Fix:** none
- **Source:** Coder's `DrupalPractice` standard
- **Ports:** `DrupalPractice.Objects.GlobalFunction.GlobalFunction`

Coder's `DrupalPractice` standard asks a class to use a service or a trait method in place of a
procedural wrapper. A test can replace an injected service, and it cannot replace a procedural
call.

**Reports:**

- a `t()` call in any class, where `$this->t()` from `StringTranslationTrait` applies;
- a call to `drupal_render()`, `drupal_get_destination()`, `format_date()`, or a `*_load()`
  function of an entity type, such as `node_load()` and `user_load()`, in a class that can get
  services injected. That is a class that extends one of Drupal's base classes, such as
  `ControllerBase` or `FormBase`, implements `ContainerInjectionInterface`, or is a service. A
  service is a class that the nearest `*.services.yml` file lists, in the class's directory or a
  directory above it.

**Not reported:**

- an interface, a trait, an enum and a static method;
- a Drupal 7 module: one whose nearest info file is a `*.info` file that names a core version below
  8, or no version.

An anonymous class inside a method is part of the class around it.

The rule reads the `*.services.yml` file from disk. Mago gives the path of each file from the
workspace, and starts the worker in the directory of its config file, so the rule finds the file
when the config file is at the root of the workspace.

??? example "Example"

    ```php
    // Reported.
    class ExampleList {

      public function label(): string {
        return (string) t('Examples');
      }

    }

    // Fine.
    use Drupal\Core\StringTranslation\StringTranslationTrait;

    class ExampleList {

      use StringTranslationTrait;

      public function label(): string {
        return (string) $this->t('Examples');
      }

    }
    ```

??? note "Compared with Coder"

    - The rule matches the base class and the interface by the last part of the name, so
      `extends \Drupal\Core\Form\FormBase` counts. Coder compares the names as written.
    - A service named after its class, as in `Drupal\mymodule\Foo: ~`, counts as a service. Coder
      reads only the `class` keys.
    - It also reports a call written in another case, such as `T()`, or with a leading backslash,
      such as `\t()`. Coder skips both.

---

## drupal/translatable-string

- **Level:** warning
- **Fix:** safe. It writes a string that escapes its own quote with the other quote, when the value
  stays the same.
- **Source:** Coder's `Drupal` standard
- **Ports:** `Drupal.Semantics.FunctionT`: `BackslashDoubleQuote`, `BackslashSingleQuote`, `Concat`, `ConcatString`, `EmptyString`, `EmptyT`, `NotLiteralString`, `WhiteSpace`

Coder asks for a translatable string that is one plain literal. The string extractor reads the
source and does not run it. The rule reads the strings of `t()`, `formatPlural()`,
`new TranslatableMarkup()` and `new TranslationWrapper()`, and of the `t()` and `formatPlural()`
methods.

**Reports:**

- a string built by concatenation or interpolation. A nowdoc counts as a literal and a heredoc
  does not;
- a string padded with whitespace, and an empty one. For a string built with `.`, the padding of
  the first literal is reported too;
- a quoted string joined with `.` right after the call, as in `t('Name') . ':'`, when the message
  is a literal;
- a literal that escapes its own quote with a backslash, as in `t('It\'s here')`, when the first
  argument of `t()` or of a markup class is a literal or starts with one.

**Not reported:**

- the padding of the later literals of a string built with `.`;
- a string joined after the call that is empty, or one of `(`, `)`, `[`, `]`, `-`, `<`, `>`, `«`,
  `»` and `\n` once its quotes, HTML tags and spaces are gone;
- an escaped quote in the strings of `formatPlural()`.

The fix applies only when the other quote keeps the value: no `"` and `$` in a single-quoted
string and no backslash but the one before the quote, and the same for a double-quoted string
without `'`.

??? example "Example"

    ```php
    // Reported.
    $message = t('Deleted ' . $name . '.');

    // Fine.
    $message = t('Deleted @name.', ['@name' => $name]);
    ```

??? note "Compared with Coder"

    - Coder has no fix for an escaped quote. `mago format` also swaps the quotes of a double-quoted
      string that escapes its own quote.
    - The rule reads the escapes in a string literal, where Coder looks for the two characters `\'`
      or `\"` in its text. A string such as `'Path \\'` ends in an escaped backslash, so it holds no
      escaped quote and is not reported.
    - The whitespace check reads the value that PHP builds from the string. An escape such as `\n`
      or `\t` at either end of a double-quoted string counts as whitespace. Coder reads the two
      characters as written and does not report it.
    - It also reports a call that Coder does not see: a fully qualified `\t()` or
      `new \Drupal\Core\StringTranslation\TranslatableMarkup()`, a function or method call written
      in another letter case such as `T()` or `$this->T()`, and a call inside a string
      interpolation.
    - It checks the strings of `formatPlural()` for padding, an empty string and a non-literal, and
      a string joined after the call. Coder does not read `formatPlural()`.
    - It reads the whole argument, so an argument that starts with a literal but is another
      expression, such as `'a' ?: 'b'`, `'a' ?? 'b'` or `'a'[0]`, is reported as not a literal.
      Coder reads only the first token and lets these through.
    - It skips `t(...$x)`, `t(string: 'x')` and the first-class callable `t(...)`, which have no
      positional first argument to read. Coder reports them as not a literal.

---

## drupal/translated-exception

- **Level:** warning
- **Fix:** none
- **Source:** Coder's `DrupalPractice` standard
- **Ports:** `DrupalPractice.General.ExceptionT.ExceptionT`

Coder's `DrupalPractice` standard asks for an exception message that does not go through `t()`.
Developers read exception text in logs, and a translation hides the original text.

**Reports:**

- an exception message passed through `t()`.

**Not reported:**

- a `t()` that a `use function` import points at another function.

??? example "Example"

    ```php
    // Reported.
    throw new \InvalidArgumentException(t('Unknown example @id.', ['@id' => $id]));

    // Fine.
    throw new \InvalidArgumentException(sprintf('Unknown example %s.', $id));
    ```

??? note "Compared with Coder"

    - The rule also reports `\t()` and `T()`, which Coder does not.
    - Coder reports a `t()` that a `use function` import points at another function.

---

## drupal/unsilenced-deprecation

- **Level:** error
- **Fix:** safe. It adds the `@`.
- **Source:** Drupal's [How to deprecate](https://www.drupal.org/about/core/policies/core-change-policies/how-to-deprecate#how-trigger-error) page
- **Ports:** `Drupal.Semantics.UnsilencedDeprecation.UnsilencedDeprecation`

Drupal's How to deprecate page writes a deprecation notice as `@trigger_error()`. Drupal's error
handler turns an unsilenced notice into a test failure in every test that runs the code.

**Reports:**

- a `trigger_error()` deprecation notice without the `@` prefix.

??? note "Compared with Coder"

    - The rule also reports calls that Coder's token matching misses: `\trigger_error()`, a call
      with a named level argument, a function name with capitals such as `TRIGGER_ERROR()`, and a
      call inside an interpolated string or heredoc.
    - For `@ trigger_error()`, the fix closes the gap after the `@`. phpcbf adds a second `@`.
