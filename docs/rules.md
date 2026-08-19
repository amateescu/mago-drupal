# Linter rules

Every rule the extension registers, grouped by what it checks, alphabetical within each group.

## Bugs and security

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/preg-security` | Error | `preg_*` patterns using the `e` modifier, which evaluates the replacement as PHP. |
| `drupal/remote-address` | Error | `$_SERVER['REMOTE_ADDR']` reads, which ignore the reverse-proxy settings. |
| `drupal/weak-hash` | Warning | `md5()`, `sha1()` and `crc32()` calls, and `hash()` called with `md5`, `sha1`, `crc32` or `crc32b`. Has an unsafe fix to `hash('xxh64', …)`. |

## Using the right API

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/deprecation-message` | Warning | `E_USER_DEPRECATED` messages that break the deprecation grammar, including the version and change-record formats. |
| `drupal/global-function` | Warning | Procedural wrappers called from inside a class, most often `t()` where `$this->t()` applies. |
| `drupal/translatable-string` | Warning | Translatable strings built by concatenation or interpolation, padded with whitespace, or empty. Covers `t()`, `formatPlural()` and `new TranslatableMarkup()`. |
| `drupal/translated-exception` | Warning | Exception messages passed through `t()`. |

## Procedural files

Only fire inside `.module` and `.install` files.

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/constant-prefix` | Warning | `define()` constants that skip the module prefix. |
| `drupal/empty-install-hook` | Error | Empty `hook_install()` and `hook_uninstall()` bodies. |
| `drupal/global-variable` | Error | Module globals that skip the leading underscore. |
| `drupal/install-hook-location` | Error | `hook_install()` and friends declared in `.module` instead of `.install`. |
| `drupal/t-in-hook-schema` | Error | `t()` calls inside `hook_schema()`. |

## Naming and imports

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/enum-case-name` | Error | Enum cases that are not UpperCamelCase. |
| `drupal/property-name` | Error | Class properties that are not lowerCamelCase. |
| `drupal/redundant-use` | Error | `use` statements importing a class from the global namespace. |

## Comment text

The slice of `Drupal.Commenting.*` that reads a comment's own text, with no need to compare it
against the declaration it documents.

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/doc-comment-array-syntax` | Warning | `array()` syntax inside a docblock `@code` example. |
| `drupal/doc-type-namespace` | Warning | `@param`, `@return`, `@var` and `@throws` types written as a use-imported short name instead of the fully qualified name. |
| `drupal/expected-exception-tag` | Warning | The legacy PHPUnit `@expectedException*` docblock tags. |
| `drupal/gender-neutral-comment` | Warning | Gendered pronouns in comments. |
| `drupal/inline-comment` | Warning | A `//` comment that starts lowercase, has no terminal punctuation, or uses `#` instead of `//`. |
| `drupal/post-statement-comment` | Warning | A `//` comment sharing a line with the statement before it. |
| `drupal/todo-comment` | Warning | A to-do comment that does not follow the `@todo Fix problem X here.` format. |

Mago's own `tagged-todo` rule requires a `TODO(@user)`/`TODO(#123)` reference, an incompatible
format from Drupal's `@todo Fix problem X here.` convention, so it is not a substitute for
`drupal/todo-comment` and the two should not both run on the same codebase.

## Docblock structure

The slice of `Drupal.Commenting.*` that reads a docblock's summary, description and tag list, but
does not need the declaration's real signature to check them.

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/class-comment` | Error | A class, interface, trait or enum with no docblock, the wrong comment style, or a summary that just repeats the name. |
| `drupal/deprecated-tag` | Warning | A `@deprecated` tag that breaks the version-and-reason grammar, or has no `@see` tag immediately after it. |
| `drupal/doc-comment` | Warning | A docblock with no summary, a summary that is not capitalized, not punctuated, or spans more than one line, or `@param` tags that are not grouped first. |
| `drupal/file-comment` | Error | A procedural file that does not open with a docblock tagged `@file`. |
| `drupal/function-comment` | Error | A function or method with no docblock, the wrong comment style, or a malformed, undescribed or uncapitalized `@param`, `@return`, `@throws` or `@see` tag. |
| `drupal/hook-comment` | Warning | A hook implementation not documented as `Implements hook_foo().`, or one that duplicates `@param`/`@return` documentation. |
| `drupal/inline-variable-comment` | Warning | An inline `@var` declaration using `//` instead of `/** */`, or writing the variable name before the type. |
| `drupal/variable-comment` | Error | A class property with no `@var` docblock, the wrong comment style, or more than one `@var` tag. |

## Drupal 7 era

These target APIs removed in Drupal 8, so they do not fire on a modern codebase. They are here
because core's `phpcs.xml.dist` still enables the matching sniffs and Coder 9 still ships them, so
leaving them out would leave a hole in the replacement.

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/link-text-translatable` | Error | Literal link text passed to `l()` without `t()`. |
| `drupal/t-in-hook-menu` | Error | `t()` calls inside `hook_menu()`. |
| `drupal/watchdog-message` | Error | `watchdog()` messages wrapped in `t()` or built by concatenation. |

## Parity notes

The two comment groups complete `Drupal.Commenting.*` except for the pure-whitespace sub-codes
`mago format` already produces, and the `DocCommentStar` gap described below.

`Drupal.Commenting.FunctionComment`'s checks against a function's real parameter list and return
type were expected to be the hard part of this family, but most of it turned out to be redundant
with `mago analyze`, which already reads `@param`/`@return`/`@var`/`@throws` as authoritative types
when there is no native hint, the same way phpstan and psalm do: it already reports an `@return
void` that returns a value, a function with no `return` at all, an `@param` naming an unknown
parameter, and most wrong-cased type aliases (`Boolean` fails to resolve as a class).
`drupal/function-comment` covers what was left: docblock presence, prose-quality checks, and the
one signature-dependent check that survives, a method with partial `@param` coverage missing an
entry for a real parameter.

One `Drupal.Commenting.*` sniff is pure whitespace and is not ported because `mago format` already
produces its result: `DocCommentAlignment` (star spacing and alignment). `DocCommentStar` (adding a
star to a docblock line that is missing one) is close but not quite covered: `mago format` leaves a
star-less line untouched rather than fixing it, which is an upstream formatter gap rather than
something a linter rule can fix, so a hand-edited docblock line without its `*` stays unfixed either
way.
