# Using the right API

Rules for calls that have a Drupal API in their place, and for the strings that Drupal's tools read
from the source.

## drupal/deprecation-message

- **Level:** warning
- **Fix:** none
- **Ports:** `Drupal.Semantics.FunctionTriggerError`: `TriggerErrorPeriodAfterSeeUrl`, `TriggerErrorSeeUrlFormat`, `TriggerErrorTextLayoutRelaxed`, `TriggerErrorTextLayoutStrict`, `TriggerErrorVersion`

An `E_USER_DEPRECATED` message of `trigger_error()` that breaks the
[deprecation grammar](https://www.drupal.org/node/2856820). Drupal's tools parse these messages,
so the wording must match:

- **Strict**, when the function, method or file has a `@deprecated` tag:
  `%thing% is deprecated in %deprecation-version% and is removed from %removal-version%. %extra-info%. See %cr-link%`
- **Relaxed**, for any other notice:
  `%thing% is deprecated in %deprecation-version% any free text %removal-version%. %extra-info%. See %cr-link%`

A version is `drupal:n.n.n`, `project:n.x-n.n` or `project:n.n.n`, with an optional release label
such as `-beta1`. The change-record link is a `drupal.org/node`, a `drupal.org/project` issue or a
`git.drupalcode.org` work item, with no punctuation after it.

The rule checks the text that the message is built from. A `sprintf()` call gives its format
string. Otherwise an interpolated string keeps its variables, and a part that is not a string, such
as `__CLASS__` or `static::class`, keeps its source text. So `__CLASS__ . ' is deprecated in ...'`
and `"$name is deprecated in ..."` have a `%thing%`. A message that starts with a variable, such as
`$name . ' is deprecated in ...'` or `$this->message`, is not checked. Coder reads messages the
same way.

For a notice at the top of a file, the rule reads the next docblock at file level, as Coder does.
For a notice in a function or method, it reads the docblock of that declaration, above or below its
attributes. A `@deprecated` tag at the start of a docblock line counts in any letter case. A
mention of the tag inside the text does not.

**Compared with Coder:**

- Coder joins the pieces of a message with spaces. It reports a valid message that a split leaves
  with two spaces, as in `'... See ' . 'https://...'`, a heredoc message, and a `\sprintf()` call.
  The rule reports none of these.
- Coder takes the nearest docblock before the call when it sits one nesting level above the call.
  A method with no docblock then takes the tag of the method above it, and a closure in a tagged
  method gets the relaxed wording. The rule uses the docblock of the enclosing function or method,
  so a method with no docblock gets the relaxed wording, and a closure gets the wording of the
  method around it.
- The rule also checks the message of `TRIGGER_ERROR()` and any other spelling in another letter
  case. PHP calls the same function, but Coder matches the name exactly and skips them.

## drupal/global-function

- **Level:** warning
- **Fix:** none
- **Ports:** `DrupalPractice.Objects.GlobalFunction.GlobalFunction`

A procedural wrapper called from inside a class, where a service or a trait method applies. The
most frequent case is `t()`, where `$this->t()` from `StringTranslationTrait` applies. A test can
replace an injected service, and it cannot replace a procedural call.

The wrappers are `t()`, `drupal_render()`, `drupal_get_destination()`, `format_date()`, and the
`*_load()` functions of entities, such as `node_load()` and `user_load()`. A Drupal 7 module is
skipped, as in Coder: one whose nearest info file is a `*.info` file that names a core version
below 8, or no version.

As in Coder, the rule reports `t()` in any class, and the other wrappers only in a class that can
get services injected. That is a class that extends one of Drupal's base classes, such as
`ControllerBase` or `FormBase`, implements `ContainerInjectionInterface`, or is a service. A
service is a class that the nearest `*.services.yml` file lists, in the class's directory or a
directory above it. An interface, a trait, an enum and a static method are not checked. An
anonymous class inside a method is part of the class around it.

The rule reads the `*.services.yml` file from disk. Mago gives the path of each file from the
workspace, and starts the worker in the directory of its config file, so the rule finds the file
when the config file is at the root of the workspace.

**Compared with Coder:**

- The rule matches the base class and the interface by the last part of the name, so
  `extends \Drupal\Core\Form\FormBase` counts. Coder compares the names as written.
- A service named after its class, as in `Drupal\mymodule\Foo: ~`, counts as a service. Coder
  reads only the `class` keys.
- It also reports a call written in another case, such as `T()`, or with a leading backslash, such
  as `\t()`. Coder skips both.

## drupal/translatable-string

- **Level:** warning
- **Fix:** safe. It writes a string that escapes its own quote with the other quote, when the value
  stays the same.
- **Ports:** `Drupal.Semantics.FunctionT`: `BackslashDoubleQuote`, `BackslashSingleQuote`, `Concat`, `ConcatString`, `EmptyString`, `EmptyT`, `NotLiteralString`, `WhiteSpace`

A translatable string that is not one plain literal. The string extractor reads the source and
does not run it. The rule reads the strings of `t()`, `formatPlural()`, `new TranslatableMarkup()`
and `new TranslationWrapper()`, and of the `t()` and `formatPlural()` methods. It reports:

- a string built by concatenation or interpolation. A nowdoc counts as a literal and a heredoc
  does not, as in Coder 9;
- a string padded with whitespace, and an empty one. For a string built with `.`, the padding of
  the first literal is reported too, as in Coder. The later literals are not checked for padding;
- a quoted string joined with `.` right after the call, as in `t('Name') . ':'`, when the message
  is a literal. A string that is empty, or one of `(`, `)`, `[`, `]`, `-`, `<`, `>`, `«`, `»` and
  `\n` once its quotes, HTML tags and spaces are gone, is fine, as in Coder 9;
- a literal that escapes its own quote with a backslash, as in `t('It\'s here')`, when the first
  argument of `t()` or of a markup class is a literal or starts with one. The strings of
  `formatPlural()` are not checked for this, as in Coder.

The fix applies only when the other quote keeps the value: no `"` and `$` in a single-quoted
string and no backslash but the one before the quote, and the same for a double-quoted string
without `'`.

**Compared with Coder:**

- Coder has no fix for an escaped quote. `mago format` also swaps the quotes of a double-quoted
  string that escapes its own quote.
- The rule reads the escapes in a string literal, where Coder looks for the two characters `\'` or
  `\"` in its text. A string such as `'Path \\'` ends in an escaped backslash, so it holds no
  escaped quote and is not reported.
- The whitespace check reads the value that PHP builds from the string. An escape such as `\n` or
  `\t` at either end of a double-quoted string counts as whitespace. Coder reads the two characters
  as written and does not report it.
- It also reports a call that Coder does not see: a fully qualified `\t()` or
  `new \Drupal\Core\StringTranslation\TranslatableMarkup()`, a function or method call written in
  another letter case such as `T()` or `$this->T()`, and a call inside a string interpolation.
- It checks the strings of `formatPlural()` for padding, an empty string and a non-literal, and a
  string joined after the call. Coder does not read `formatPlural()`.
- It reads the whole argument, so an argument that starts with a literal but is another
  expression, such as `'a' ?: 'b'`, `'a' ?? 'b'` or `'a'[0]`, is reported as not a literal. Coder
  reads only the first token and lets these through.
- It skips `t(...$x)`, `t(string: 'x')` and the first-class callable `t(...)`, which have no
  positional first argument to read. Coder reports them as not a literal.

## drupal/translated-exception

- **Level:** warning
- **Fix:** none
- **Ports:** `DrupalPractice.General.ExceptionT.ExceptionT`

An exception message passed through `t()`. Developers read exception text in logs, and a
translation hides the original text.

**Compared with Coder:** the rule also reports `\t()` and `T()`, which Coder does not. It skips a
`t()` that a `use function` import points at another function, which Coder reports.

## drupal/unsilenced-deprecation

- **Level:** error
- **Fix:** safe. It adds the `@`.
- **Ports:** `Drupal.Semantics.UnsilencedDeprecation.UnsilencedDeprecation`

A `trigger_error()` deprecation notice without the `@` prefix. Drupal's error handler turns an
unsilenced notice into a test failure in every test that runs the code.

**Compared with Coder:**

- The rule also reports calls that Coder's token matching misses: `\trigger_error()`, a call with
  a named level argument, a function name with capitals such as `TRIGGER_ERROR()`, and a call
  inside an interpolated string or heredoc.
- For `@ trigger_error()`, the fix closes the gap after the `@`. phpcbf adds a second `@`.
