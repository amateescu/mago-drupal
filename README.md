# mago-drupal

A [Mago](https://github.com/carthage-software/mago) extension that contributes Drupal-specific
knowledge to the linter and analyzer.

## Requirements

PHP 8.1 or later, and Mago 1.48 or later for the extension API.

## Install

```shell
composer require --dev carthage-software/mago amateescu/mago-drupal
```

Then register the shipped worker in `mago.toml` and widen the scanned extensions:

```toml
[source]
extensions = ["php", "module", "install", "inc", "theme", "profile", "engine"]

[extension-hosts.drupal]
command = ["php", "vendor/amateescu/mago-drupal/resources/worker.php"]
```

Add `"--core"` to the command when analysing Drupal core itself, which enables rules that only
apply to core. Add `"--root=PATH"` when the Drupal document root is somewhere other than the
project directory, `web/`, `docroot/`, `html/`, `public/` or the Composer scaffold's `web-root`.
Parsed indexes are cached under the system temporary directory; `MAGO_DRUPAL_CACHE=/dir` moves the
cache and `MAGO_DRUPAL_CACHE=0` turns it off (see [docs/analyzer.md](docs/analyzer.md#cost)).

## What it provides

44 linter rules, in groups by what they check. [docs/rules.md](docs/rules.md) describes every rule.
The codes below omit their shared `drupal/` prefix.

- **Bugs and security**: `insecure-unserialize`, `preg-security`, `remote-address`, `weak-hash`.
- **Using the right API**: `deprecation-message`, `discouraged-function`, `global-function`,
  `render-callback`, `symfony-yaml-parse`, `translatable-string`, `translated-exception`,
  `unsilenced-deprecation`.
- **Procedural files**. These rules report only in `.module` and `.install` files:
  `constant-prefix`, `empty-install-hook`, `global-variable`, `install-hook-location`,
  `t-in-hook-schema`.
- **Naming, imports and syntax**: `else-if`, `enum-case-name`, `fully-qualified-name`,
  `method-visibility`, `property-name`, `redundant-use`, `use-leading-backslash`.
- **Comment text**: `author-tag`, `comment-line-length`, `doc-comment-array-syntax`,
  `doc-type-namespace`, `expected-exception-tag`, `gender-neutral-comment`, `inline-comment`,
  `post-statement-comment`, `todo-comment`.
- **Docblock structure**: `class-comment`, `deprecated-tag`, `doc-comment`, `file-comment`,
  `function-comment`, `hook-comment`, `inline-variable-comment`, `variable-comment`.
- **Drupal 7 era**. Core's `phpcs.xml.dist` still enables the matching sniffs, so these rules stay:
  `link-text-translatable`, `t-in-hook-menu`, `watchdog-message`.

The two comment groups complete `Drupal.Commenting.*`. The
[parity notes](docs/rules.md#parity-notes) give the details and name the two sniffs that
`mago format` covers.

Rule codes are stable. Projects that we do not control write them into baselines and into
`// @mago-expect lint:<code>` comments. For that reason, the codes get no vendor prefix and no new
name.

The `drupal` analyzer plugin types what Drupal's runtime wiring hands back, without booting Drupal:

- **Container lookups**: `$container->get('entity_type.manager')` and `\Drupal::service('renderer')`
  return the service's class instead of `?object`, read from every `*.services.yml` and
  `*ServiceProvider.php` in the project. Deprecated and unknown services are reported on lookup.
- **Entity handlers and entities**: `getStorage('node')->load(1)` is a `Node|null`, read from the
  `#[ContentEntityType]` and `#[ConfigEntityType]` attributes Mago has scanned, and an id no entity
  type declares is reported.
- **Deprecation scopes**: deprecation issues are dropped inside a `@group legacy` test, under
  `#[IgnoreDeprecations]`, in the arguments of `DeprecationHelper::backwardsCompatibleCall()`, and
  inside deprecated code, which includes a method that overrides a deprecated one.
- **Sharper core signatures**: `$url->toString()` is a `string` and `$url->toString(TRUE)` a
  `GeneratedUrl`; a cache item is a shape, not a bare `object`.
- **Magic entity fields**: `$node->field_thing` is a `FieldItemListInterface`, and neither
  `$node->field_thing->value` nor `$item->value` reads as an undefined property, the way
  `ContentEntityBase::__get()` and `FieldItemBase::__get()` behave at runtime.
- **Form responses**: a `Response` returned from `buildForm()` is not an invalid return, since the
  form builder sends it instead of the page.
- **Calls from traits**: `$this->getEntity()` in a trait is typed from the classes that use the
  trait, the way PHPStan checks a trait's body in each of them, and `$this->container` is not
  reported as missing when they all have it.
- **Config**: `\Drupal::config('system.maintenance')->get('message')` is a `string|null`, read from
  the config schema, a mapping is an array shape of its keys, `$storage->read('core.extension')` is
  the whole object's shape or `false`, and a key the schema does not list is reported.
- **Plugins**: `$blockManager->createInstance('page_title_block')` is a `PageTitleBlock`, read from
  the plugin attributes, and an id no plugin declares is reported.
- **Drupal-specific checks**: the phpstan-drupal rule set, from hook signatures and test class
  conventions to plugin manager setup, reported as `drupal/<code>` analysis issues.

A second plugin, `phpunit`, knows nothing about Drupal. It narrows `assertNotEmpty()` and
`assertEmpty()`, types mocks documented as `X|MockObject` and the calls that set up a Prophecy
double, and reports mocks and prophecies documented with the wrong type. A third, `phpstan-ignores`, is off by default; turned on, it lets `@phpstan-ignore` comments
drop the Mago issues that report the same finding.

[docs/analyzer.md](docs/analyzer.md) has the details and the limits.

## Replacing phpcs

This extension only covers the parts of Drupal's standard that need to know about Drupal. Most of
it is generic PHP style that Mago already handles:

| Part of the standard | Checked by |
| --- | --- |
| Whitespace, indentation, braces, code line width, docblock star alignment | `mago format` |
| Class, interface, function and file naming | Mago's naming rules |
| Function aliases, unused imports | `no-alias-function`, `no-redundant-use`, enabled by default |
| Unreachable code, deprecated PHP functions, wrong `@param` and `@return` types | `mago analyze` |
| Everything specific to Drupal | This extension |

Some of these checks need one line of configuration to match Drupal and not Mago's defaults:

```toml
[formatter]
# Drupal's style for braces, indentation and line length.
preset = "drupal"

[linter]
# Mago's own Drupal switch. The formatter's drupal preset writes TRUE, FALSE and NULL. This switch
# stops `lowercase-keyword` from reporting those three keywords. The rule still reports every other
# upper-case keyword.
integrations = ["drupal"]

[linter.rules]
# Report an interface name without the "Interface" suffix.
interface-name = { psr = true }
# Report a file whose name differs from the class that it declares.
file-name = { enabled = true }
# Accept snake_case functions and camelCase methods. The exclude covers Drupal's hook
# documentation. Those hook names have uppercase placeholders such as hook_ENTITY_TYPE_insert().
function-name = { either = true, exclude = ["*.api.php"] }
# Report a variable that is assigned and never read. That is the part of
# DrupalPractice.CodeAnalysis.VariableAnalysis that core enables.
no-redundant-variable = { enabled = true }

[analyzer]
# Report a PHP function that is called with the wrong case. That is Drupal's
# Squiz.PHP.LowercasePHPFunctions.
check-name-casing = true
```

Mago has many more rules than Drupal's standard. A module that passes phpcs starts with issues that
the standard does not ask for, from rules such as `assert-description` or `no-else-clause`. When
you disable those rules, you lose no check that phpcs did:

```toml
[linter.rules]
# These rules contradict Drupal's standard, or repeat a rule of this extension.
# Drupal writes a deprecation as @trigger_error(), and drupal/unsilenced-deprecation reports a
# missing @.
# drupal/inline-comment already reports a "#" comment. drupal/todo-comment already checks the
# "@todo Fix problem X here." format, and tagged-todo contradicts that format.
no-error-control-operator = { enabled = false }
no-hash-comment = { enabled = false }
tagged-todo = { enabled = false }

# Advice that Drupal's standard does not ask for. Keep the rules that you want.
assert-description = { enabled = false }
braced-string-interpolation = { enabled = false }
cyclomatic-complexity = { enabled = false }
excessive-nesting = { enabled = false }
excessive-parameter-list = { enabled = false }
halstead = { enabled = false }
identity-comparison = { enabled = false }
kan-defect = { enabled = false }
literal-named-argument = { enabled = false }
no-assign-in-condition = { enabled = false }
no-boolean-flag-parameter = { enabled = false }
no-else-clause = { enabled = false }
no-empty = { enabled = false }
no-empty-catch-clause = { enabled = false }
no-isset = { enabled = false }
no-multi-assignments = { enabled = false }
no-shorthand-ternary = { enabled = false }
prefer-arrow-function = { enabled = false }
prefer-early-continue = { enabled = false }
prefer-first-class-callable = { enabled = false }
prefer-static-closure = { enabled = false }
readable-literal = { enabled = false }
too-many-methods = { enabled = false }
# Drupal core mostly does not declare strict types, but many contrib modules do. Examine your own
# codebase before you disable this rule.
strict-types = { enabled = false }
```

Two checks still need phpcs:

- `Drupal.InfoFiles.*` and `DrupalPractice.InfoFiles.NamespacedDependency` check `.info.yml` files.
  Mago cannot report an issue in a YAML file.
- `DrupalPractice.Objects.GlobalDrupal` reports a `\Drupal::service()` call where injection is
  possible. That check needs the analyzer, not a linter rule.

The parity target is [Coder 9](https://www.drupal.org/project/coder/releases/9.0.0).

## Development

```shell
composer install
just check-all
```

`just check` runs `validate`, `format-check`, `test`, `lint` and `analyze`. `just check-all` also
runs `test-corpus`. That recipe starts a real worker over `tests/corpus/src/` and fails if an
`@mago-expect` annotation is not fulfilled. `tests/corpus/expected-rules.txt` pins the registered
rule codes. Add your code there when you add a rule. Set `MAGO=/path/to/mago` to run the checks
against a different build.

## License

MIT.
