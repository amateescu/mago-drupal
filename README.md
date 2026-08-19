# mago-drupal

A [Mago](https://github.com/carthage-software/mago) extension that contributes Drupal-specific
knowledge to the linter and analyzer.

## Requirements

PHP 8.1 or later, and Mago 1.47 or later for the extension API.

## Install

```shell
composer require --dev carthage-software/mago amateescu/mago-drupal
```

Register the shipped worker in `mago.toml`:

```toml
[extension-hosts.drupal]
command = ["php", "vendor/amateescu/mago-drupal/resources/worker.php"]
```

Drupal keeps procedural code in files PHP does not name `.php`, and several rules only fire inside
those, so widen the scanned extensions too:

```toml
[source]
extensions = ["php", "module", "install", "inc", "theme", "profile", "engine"]
```

Add `"--core"` to the command when analysing Drupal core itself, which enables rules that only
apply to core.

## What it provides

### Linter rules

Grouped by what they check, alphabetical within each group.

**Bugs and security**

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/preg-security` | Error | `preg_*` patterns using the `e` modifier, which evaluates the replacement as PHP. |
| `drupal/remote-address` | Error | `$_SERVER['REMOTE_ADDR']` reads, which ignore the reverse-proxy settings. |
| `drupal/weak-hash` | Warning | `md5()`, `sha1()` and `crc32()` calls, and `hash()` called with `md5`, `sha1`, `crc32` or `crc32b`. Has an unsafe fix to `hash('xxh64', …)`. |

**Using the right API**

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/deprecation-message` | Warning | `E_USER_DEPRECATED` messages that break the deprecation grammar, including the version and change-record formats. |
| `drupal/global-function` | Warning | Procedural wrappers called from inside a class, most often `t()` where `$this->t()` applies. |
| `drupal/translatable-string` | Warning | Translatable strings built by concatenation or interpolation, padded with whitespace, or empty. Covers `t()`, `formatPlural()` and `new TranslatableMarkup()`. |
| `drupal/translated-exception` | Warning | Exception messages passed through `t()`. |

**Procedural files**

Only fire inside `.module` and `.install` files.

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/constant-prefix` | Warning | `define()` constants that skip the module prefix. |
| `drupal/empty-install-hook` | Error | Empty `hook_install()` and `hook_uninstall()` bodies. |
| `drupal/global-variable` | Error | Module globals that skip the leading underscore. |
| `drupal/install-hook-location` | Error | `hook_install()` and friends declared in `.module` instead of `.install`. |
| `drupal/t-in-hook-schema` | Error | `t()` calls inside `hook_schema()`. |

**Naming and imports**

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/enum-case-name` | Error | Enum cases that are not UpperCamelCase. |
| `drupal/property-name` | Error | Class properties that are not lowerCamelCase. |
| `drupal/redundant-use` | Error | `use` statements importing a class from the global namespace. |

**Comments, read on their own**

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

**Comments, checked against their docblock's own structure**

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

This completes `Drupal.Commenting.*` except for the pure-whitespace sub-codes `mago format` already
produces, and the one formatter gap noted above.

**Drupal 7 era**

These target APIs removed in Drupal 8, so they do not fire on a modern codebase. They are here
because core's `phpcs.xml.dist` still enables the matching sniffs and Coder 9 still ships them, so
leaving them out would leave a hole in the replacement.

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/link-text-translatable` | Error | Literal link text passed to `l()` without `t()`. |
| `drupal/t-in-hook-menu` | Error | `t()` calls inside `hook_menu()`. |
| `drupal/watchdog-message` | Error | `watchdog()` messages wrapped in `t()` or built by concatenation. |

Rule codes are stable. They get written into baselines and `// @mago-expect lint:<code>` comments in
projects we don't control, so they will not be vendor-prefixed or renamed.

### Analyzer plugin

The `drupal` plugin is registered but does not contribute providers yet. Everything it needs
depends on an index built from `*.services.yml`, `*.routing.yml`, plugin attributes and
`*.schema.yml`, so that index comes first. See the `@todo` list in `src/Analyzer/DrupalPlugin.php`.

## Replacing phpcs

This extension only covers the parts of Drupal's standard that need to know about Drupal. Most of
it is generic PHP style that Mago already handles:

| Part of the standard | Handled by |
| --- | --- |
| Whitespace, indentation, braces, line length, docblock star alignment | `mago format` |
| Class, interface, function and file naming | Mago's naming rules |
| Function aliases, unused imports | `no-alias-function`, `no-redundant-use`, on by default |
| Unused variables, unreachable code, deprecated PHP functions | `mago analyze` |
| Everything Drupal-specific | This extension |

Mago's own `tagged-todo` rule requires a `TODO(@user)`/`TODO(#123)` reference, an incompatible
format from Drupal's `@todo Fix problem X here.` convention, so it is not a substitute for
`drupal/todo-comment` and the two should not both run on the same codebase.

Three of those need a line of configuration to match Drupal rather than Mago's defaults:

```toml
[formatter]
# Drupal's brace, indentation and line-length style.
preset = "drupal"

[linter.rules]
# Require the "Interface" suffix on interface names.
interface-name = { psr = true }
# Require a file to be named after the class it declares.
file-name = { enabled = true }
# Accept snake_case functions alongside camelCase methods.
function-name = { either = true }
```

### What still needs phpcs

One group left, so the phpcs job cannot go away entirely yet: `Drupal.InfoFiles.*` and
`DrupalPractice.InfoFiles.NamespacedDependency` read `.info.yml`. Mago parses PHP, so an extension
can read YAML to build an index but cannot anchor an issue inside a YAML file. Those five checks fit
better as a PHPUnit test than as a linter rule.

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

Parity is measured against [Coder 9](https://www.drupal.org/project/coder/releases/9.0.0).

## Development

```shell
composer install
just check
```

`just check` runs `validate`, `format-check`, `test`, `lint` and `analyze`. `just check-all` adds
`test-corpus`, which starts a real worker and checks the inline `@mago-expect` annotations in
`tests/corpus/src/`. An unfulfilled expectation is reported as an issue, so a clean corpus run means
every rule fired exactly where the fixtures say it should.

`tests/corpus/expected-rules.txt` pins every rule code the extension registers, and `test-corpus`
compares Mago's registration against it before linting. The lint run is narrowed to those codes so
the built-in rules cannot fail the deliberately-bad fixtures, and narrowing skips the expectations
for every rule left out, so without the pin a rule could stop registering and take its fixture
coverage with it. Add the code there when you add a rule.

Every recipe reads the `MAGO` env variable, so the checks can run against a build other than
`vendor/bin/mago`, such as a local one:

```shell
MAGO=/path/to/mago just check-all
```

## License

MIT.
