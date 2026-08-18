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
| Whitespace, indentation, braces, line length | `mago format` |
| Class, interface, function and file naming | Mago's naming rules |
| Function aliases, unused imports, `@todo` format | `no-alias-function`, `no-redundant-use`, `tagged-todo`, on by default |
| Unused variables, unreachable code, deprecated PHP functions | `mago analyze` |
| Everything Drupal-specific | This extension |

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

Two groups, so the phpcs job cannot go away entirely yet.

`Drupal.InfoFiles.*` and `DrupalPractice.InfoFiles.NamespacedDependency` read `.info.yml`. Mago
parses PHP, so an extension can read YAML to build an index but cannot anchor an issue inside a
YAML file. Those five checks fit better as a PHPUnit test than as a linter rule.

The `Drupal.Commenting.*` family is not ported yet.

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

Every recipe reads the `MAGO` env variable. With `dev-main` installed the override is required, not
optional: Composer's mago binary downloader only resolves tagged releases, so `vendor/bin/mago`
cannot be provisioned until 1.47 is tagged. Point `MAGO` at a local build:

```shell
MAGO=/path/to/mago just check-all
```

## License

MIT.
