# mago-drupal

A [Mago](https://github.com/carthage-software/mago) extension that contributes Drupal-specific
knowledge to the linter and analyzer.

## Requirements

PHP 8.1 or later, and Mago 1.47 or later for the extension API.

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
apply to core.

## What it provides

33 linter rules, grouped by what they check. [docs/rules.md](docs/rules.md) describes every rule;
the codes below drop their shared `drupal/` prefix.

- **Bugs and security**: `preg-security`, `remote-address`, `weak-hash`.
- **Using the right API**: `deprecation-message`, `global-function`, `translatable-string`,
  `translated-exception`.
- **Procedural files**, firing only in `.module` and `.install` files: `constant-prefix`,
  `empty-install-hook`, `global-variable`, `install-hook-location`, `t-in-hook-schema`.
- **Naming and imports**: `enum-case-name`, `property-name`, `redundant-use`.
- **Comment text**: `doc-comment-array-syntax`, `doc-type-namespace`, `expected-exception-tag`,
  `gender-neutral-comment`, `inline-comment`, `post-statement-comment`, `todo-comment`.
- **Docblock structure**: `class-comment`, `deprecated-tag`, `doc-comment`, `file-comment`,
  `function-comment`, `hook-comment`, `inline-variable-comment`, `variable-comment`.
- **Drupal 7 era**, kept because core's `phpcs.xml.dist` still enables the matching sniffs:
  `link-text-translatable`, `t-in-hook-menu`, `watchdog-message`.

The two comment groups complete `Drupal.Commenting.*`; the details, and the two sniffs left to
`mago format`, are in the [parity notes](docs/rules.md#parity-notes).

Rule codes are stable. They get written into baselines and `// @mago-expect lint:<code>` comments in
projects we don't control, so they will not be vendor-prefixed or renamed.

The `drupal` analyzer plugin is registered but does not contribute providers yet. Everything it
needs depends on an index built from `*.services.yml`, `*.routing.yml`, plugin attributes and
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

One group left, so the phpcs job cannot go away entirely yet: `Drupal.InfoFiles.*` and
`DrupalPractice.InfoFiles.NamespacedDependency` read `.info.yml`. Mago parses PHP, so an extension
can read YAML to build an index but cannot anchor an issue inside a YAML file. Those five checks fit
better as a PHPUnit test than as a linter rule.

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
