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

For a notice at the top of a file, the rule reads the next docblock at file level, as Coder does.

## drupal/global-function

- **Level:** warning
- **Fix:** none
- **Ports:** `DrupalPractice.Objects.GlobalFunction.GlobalFunction`

A procedural wrapper called from inside a class, interface, trait or enum, where a service or a
trait method applies. The most frequent case is `t()`, where `$this->t()` from
`StringTranslationTrait` applies. A test can replace an injected service, and it cannot replace a
procedural call.

The wrappers are `t()`, `drupal_render()`, `drupal_get_destination()`, `format_date()`, and the
`*_load()` functions of entities, such as `node_load()` and `user_load()`.

**Compared with Coder:** Coder reports only in a class. It reports a wrapper other than `t()` only
when the class extends one of Drupal's base classes, such as `ControllerBase` or `FormBase`,
implements `ContainerInjectionInterface`, or is a service in the module's `services.yml`. The rule
reports in every class, interface, trait, enum and anonymous class, and does not read
`services.yml`. It also reports a call written in another case, such as `T()`, or with a leading
backslash, such as `\t()`. Coder skips both. Both skip a call in a static method.

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
- a string padded with whitespace, and an empty one;
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
- It also reports a call that Coder does not see: a fully qualified `\t()` or
  `new \Drupal\Core\StringTranslation\TranslatableMarkup()`, a call written in another letter case
  such as `T()`, and a call inside a string interpolation.
- It reports a string joined after a `formatPlural()` call, which Coder does not.

## drupal/translated-exception

- **Level:** warning
- **Fix:** none
- **Ports:** `DrupalPractice.General.ExceptionT.ExceptionT`

An exception message passed through `t()`. Developers read exception text in logs, and a
translation hides the original text.

## drupal/unsilenced-deprecation

- **Level:** error
- **Fix:** safe. It adds the `@`.
- **Ports:** `Drupal.Semantics.UnsilencedDeprecation.UnsilencedDeprecation`

A `trigger_error()` deprecation notice without the `@` prefix. Drupal's error handler turns an
unsilenced notice into a test failure in every test that runs the code.

**Compared with Coder:** the rule also reports `\trigger_error()`, which Coder does not.
