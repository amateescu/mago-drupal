# DrupalPractice checks

These rules port sniffs of Coder's `DrupalPractice` standard not run by core's `phpcs.xml.dist`, so
the worker's [`--core` argument](../setup/worker-arguments.md#checking-drupal-core) turns them off.

Coder's `Project` class reads the nearest `*.info.yml` or `*.info` file, to find the module name of
a file that isn't named after it and to skip a Drupal 7 module. The rules read the same file, from
the worker's directory, which is the directory of the Mago config file:

- `drupal/class-prefix` and `drupal/form-alter-comment` read the module name from it;
- `drupal/global-constant`, `drupal/request-superglobal` and `drupal/global-function` skip a file
  whose info file is a `*.info` file that names a core version below 8, or no version, as Coder
  does.

---

## drupal/class-prefix

- **Level:** warning
- **Fix:** none
- **Source:** Coder's `DrupalPractice` standard
- **Ports:** `DrupalPractice.General.ClassName.ClassPrefix`
- **Off with `--core`**

DrupalPractice asks a class or interface outside a namespace to start with the module name.

**Reports:**

- a class or interface outside a namespace whose name doesn't start with the module name, with or
  without the underscores of the name.

A `.module`, `.install`, `.profile` or `.theme` file takes the module name from its file name. Any
other file takes it from the nearest `*.info.yml` file, or a Drupal 7 `*.info` file, in its
directory or one above.

**Not reported:**

- a file with neither a module file name nor an info file;
- every declaration from the first `namespace` statement on;
- traits, enums and anonymous classes.

??? example "Example"

    ```php
    // Reported, in example.module.
    class Helper {
    }

    // Fine.
    class ExampleHelper {
    }
    ```

??? note "Compared with Coder"

    - For `foo.bar.module`, Coder uses `foo.bar` as the module name, and the rule uses `foo`, the
      part before the first dot.

---

## drupal/curl-ssl-verify

- **Level:** warning
- **Fix:** none
- **Source:** Coder's `DrupalPractice` standard
- **Ports:** `DrupalPractice.FunctionCalls.CurlSslVerifier.SslPeerVerificationDisabled`
- **Off with `--core`**

DrupalPractice asks not to turn off curl's check of the server's SSL certificate.

**Reports:**

- a `curl_setopt()` call that sets `CURLOPT_SSL_VERIFYPEER` to `FALSE` or `0`, as in
  `curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE)`. The rule reads the arguments by position or by
  name.

**Not reported:**

- a call that spreads its arguments.

??? note "Compared with Coder"

    - The rule ignores the case of the function name and of `FALSE`, and accepts a leading
      backslash, a value in parentheses and a zero in any integer base.
    - It reads the whole value, so `FALSE ?: TRUE` doesn't count. A method call doesn't count,
      `$object?->curl_setopt()` included.
    - It reads named arguments. Coder reads the arguments by position only.
    - It skips a call with too few arguments and a call that spreads its arguments. Coder stops
      checking the file at either one.

---

## drupal/escaped-placeholder

- **Level:** warning
- **Fix:** potentially unsafe. It replaces the call with its argument, which changes the text that
  users see.
- **Source:** Coder's `DrupalPractice` standard
- **Ports:** `DrupalPractice.FunctionCalls.TCheckPlain.CheckPlain`
- **Off with `--core`**

The `@`, `%` and `:` placeholders escape their value. A value passed through `Html::escape()`, or
through `check_plain()` in Drupal 7, is then escaped twice, and a label such as `Q&A` shows as
`Q&amp;A`.

**Reports:**

- `Html::escape()` or `check_plain()` as the value of an `@`, `%` or `:` placeholder, in the
  placeholder array of `t()`, `$this->t()`, `formatPlural()`, `watchdog()`,
  `new TranslatableMarkup()`, `new PluralTranslatableMarkup()`, `new TranslationWrapper()` or
  `new FormattableMarkup()`. Either array syntax counts. `Html` must resolve to
  `Drupal\Component\Utility\Html`, through an import under any name or written in full;
- an escape call that starts a longer value, as in `Html::escape($label) . ' (draft)'`.

**Not reported:**

- an escape call after a `.`, as in `'Draft: ' . Html::escape($label)`, which escapes only a part
  of the value;
- the value of a `!` placeholder, which Drupal 7 inserts as it is.

The fix applies only when the escape call is the whole value and has one argument, with no comment
inside its parentheses.

??? example "Example"

    ```php
    // Reported.
    use Drupal\Component\Utility\Html;

    $text = t('@label tokens.', ['@label' => Html::escape($label)]);

    // Fine.
    $text = t('@label tokens.', ['@label' => $label]);
    ```

??? note "Compared with Coder"

    - Coder reads only `check_plain()`, the Drupal 7 function, in `t()` and `watchdog()` with an
      `array()` of placeholders. The rule also reads `Html::escape()`, the other calls listed above,
      a short array, `\t()` and `\check_plain()`.
    - Coder reports a `!` placeholder too, where the escape is needed.
    - Coder reports a `check_plain()` anywhere in the array that doesn't have a `.` right before it,
      such as one inside another call. The rule reads only the value of each placeholder.

---

## drupal/form-alter-comment

- **Level:** warning
- **Fix:** none
- **Source:** Coder's `DrupalPractice` standard
- **Ports:** `DrupalPractice.FunctionDefinitions.FormAlterDoc.Different`
- **Off with `--core`**

DrupalPractice asks that only the module's own `hook_form_alter()` be documented as
`Implements hook_form_alter().`.

**Reports:**

- a function with a docblock line that starts with `Implements hook_form_alter().` and a name other
  than the module name and `_form_alter`. The module name comes from the file name or the nearest
  info file, as in `drupal/class-prefix`.

**Not reported:**

- a docblock that isn't right above the `function` keyword, with a modifier, attribute or comment
  between them.

??? example "Example"

    ```php
    // Reported, in example.module.
    /**
     * Implements hook_form_alter().
     */
    function example_form_node_form_alter(array &$form) {
    }

    // Fine.
    /**
     * Implements hook_form_FORM_ID_alter().
     */
    function example_form_node_form_alter(array &$form) {
    }
    ```

??? note "Compared with Coder"

    - It compares the function name without regard to case, because PHP does.
    - It finds the hook line only at the start of a docblock line, so
      `@see Implements hook_form_alter().` isn't a hook line.

---

## drupal/global-constant

- **Level:** warning
- **Fix:** none
- **Source:** Coder's `DrupalPractice` standard
- **Ports:**
    - `DrupalPractice.Constants.GlobalConstant.GlobalConstant`
    - `DrupalPractice.Constants.GlobalDefine.GlobalConstant`
- **Off with `--core`**

DrupalPractice asks for constants on a class or interface, not in the global namespace.

**Reports:**

- a `const` statement at the top level of a file;
- a `define()` call at the top level of a `.module` file.

A statement in a class, a function, a closure, an arrow function, a block of `if`, `switch`, `try`,
a loop or `declare`, or a braced namespace isn't at the top level.

**Not reported:**

- a statement with a docblock with a `@deprecated` tag right above it. The tag must be at the start
  of a docblock line, and its name is case-sensitive. A one-line docblock with only the tag counts,
  and a tag in the middle of a sentence doesn't;
- a Drupal 7 module.

Drupal's standard allows a global constant whose name starts with the module name, and asks for
`const` over `define()`. DrupalPractice, and this rule, still report it.

??? example "Example"

    ```php
    // Reported, in example.module.
    const EXAMPLE_LIMIT = 10;

    // Fine.
    class ExampleSettings {

      const LIMIT = 10;

    }
    ```

??? note "Compared with Coder"

    - The rule treats the body of an `if`, `elseif` or `else` written without braces, and an arrow
      function, as nested. Coder doesn't see an enclosing scope there.
    - It also reports `\define()` and `DEFINE()`, which are missed by Coder. It doesn't report
      `$object?->define()` or `define(...)`.
    - It keeps the exemption of a `@deprecated` docblock that is above an attribute list.

---

## drupal/request-superglobal

- **Level:** error
- **Fix:** none
- **Source:** Coder's `DrupalPractice` standard
- **Ports:** `DrupalPractice.Variables.GetRequestData`: `SuperglobalAccessed`, `SuperglobalAccessedWithVar`
- **Off with `--core`**

DrupalPractice asks for the request object in place of the `$_GET`, `$_POST`, `$_COOKIE`, `$_FILES`
and `$_REQUEST` superglobals.

**Reports:**

- a use of `$_GET`, `$_POST`, `$_COOKIE` or `$_FILES`, including a write, a parameter or `global`
  name, and a use inside a string.

The message names the matching property of the request, and gives the source text of the key.

**Not reported:**

- `$_REQUEST`, which is left to Mago's `no-request-variable`;
- a Drupal 7 module.

??? example "Example"

    ```php
    // Reported.
    $page = $_GET['page'] ?? 0;

    // Fine.
    $page = \Drupal::request()->query->get('page', 0);
    ```

??? note "Compared with Coder"

    - The rule reports a use inside a double-quoted string or a heredoc, which is missed by Coder.
    - It treats `$_GET /* note */ ['a']` as an access with a key.

---

## drupal/strict-config-schema

- **Level:** error
- **Fix:** none
- **Source:** Coder's `DrupalPractice` standard
- **Ports:** `DrupalPractice.Objects.StrictSchemaDisabled.StrictConfigSchema`
- **Off with `--core`**

DrupalPractice asks test classes to keep the strict check of the config schema on.

**Reports:**

- a `$strictConfigSchema` property of a test class, as in `protected $strictConfigSchema = FALSE;`,
  where the first of `TRUE`, `FALSE` and `NULL` in the default isn't `TRUE`, or where there is none.

A test class has `Test` or `Tests` as a word of its name. For a property of an anonymous class, the
rule uses the nearest named class or trait.

**Not reported:**

- a class whose name only contains `Test` inside a word, such as `Testimonial` or `Testable`;
- an anonymous class outside any named class or trait.

??? note "Compared with Coder"

    - Coder takes a class with `Test` anywhere in its name, such as `Testimonial` or `Testable`, as
      a test class.
    - For a property of an anonymous class, Coder uses the outermost scope, and reads the first
      name after its `class` keyword, such as `FooTest` in `new class extends FooTest`.

---

## drupal/untranslated-description

- **Level:** warning
- **Fix:** none
- **Source:** Coder's `DrupalPractice` standard
- **Ports:** `DrupalPractice.General.DescriptionT.DescriptionT`
- **Off with `--core`**

DrupalPractice asks for a `#description` text that runs through `t()`. Users read the description
under a form element, so it must be translatable. There isn't a fix, because a class takes
`$this->t()` where a function takes `t()`.

**Reports:**

- a plain string as the value of a `#description` key, in an array of either syntax, or assigned
  to the key, as in `$form['name']['#description'] = 'Some text'`. A string counts when it has
  more than three characters once its HTML tags are gone. The first string of a concatenation
  counts, as in `'Some text ' . $more`.

**Not reported:**

- a string in double quotes that holds a variable, and a heredoc;
- a value that starts with something other than a string, such as `t()`, a variable, a ternary or
  a nested array;
- a string added with `.=`.

??? example "Example"

    ```php
    // Reported.
    $form['name'] = [
      '#type' => 'textfield',
      '#description' => 'The name shown on your profile.',
    ];

    // Fine.
    $form['name'] = [
      '#type' => 'textfield',
      '#description' => t('The name shown on your profile.'),
    ];
    ```

??? note "Compared with Coder"

    - For a `#description` string in double quotes that holds a variable, Coder reports the next
      plain string of the statement instead, which can be the value of another key. The rule skips
      that string.

---

## drupal/untranslated-options

- **Level:** warning
- **Fix:** none
- **Source:** Coder's `DrupalPractice` standard
- **Ports:** `DrupalPractice.General.OptionsT.OptionsValue`
- **Off with `--core`**

DrupalPractice asks for `#options` labels that run through `t()`.

**Reports:**

- a plain string label in the `#options` of an element with `#type` `checkboxes`, `radios`,
  `select` or `tableselect`. A label counts when it isn't a number and has more than three
  characters. Labels in nested option groups count.

**Not reported:**

- an `#options` array assigned on its own, as in `$form['x']['#options'] = [...]`.

??? example "Example"

    ```php
    // Reported.
    $form['status'] = [
      '#type' => 'select',
      '#options' => ['published' => 'Published', 'draft' => 'Draft'],
    ];

    // Fine.
    $form['status'] = [
      '#type' => 'select',
      '#options' => ['published' => t('Published'), 'draft' => t('Draft')],
    ];
    ```

??? note "Compared with Coder"

    - The rule takes the `#type` from the array that holds the `#options`, in either order and in
      either quote style. Coder takes the first `'#type'` of the statement.
    - It reports the last label of an array, a label with a comment before its comma, and a label in
      an `array()` group, which are skipped by Coder, and it doesn't need a comma after the label.
      It accepts `Array(`.
    - Coder reads a `$form['x']['#options'] = [...]` assignment when a `'#type'` is in the same
      statement.
