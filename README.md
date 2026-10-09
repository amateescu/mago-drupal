# mago-drupal

A [Mago](https://github.com/carthage-software/mago) extension that contributes Drupal-specific
knowledge to the linter and analyzer.

## Requirements

PHP 8.1 or later, and Mago 1.52 or later.

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
apply to core. It also turns off `case-fall-through`, `class-prefix`, `const-prefix`,
`curl-ssl-verify`, `form-alter-comment`, `function-prefix`, `global-constant`, `hook-attribute-name`,
`inline-comment-blank-line`, `inline-comment-punctuation`, `long-description-punctuation`,
`method-name-underscore`, `request-superglobal`, `short-list`, `strict-config-schema` and
`untranslated-options`, because core's `phpcs.xml.dist` turns off or does not run the checks that
they port.

Mago does not take this extension's rule codes under `[linter.rules]`. To turn rules off, add
`"--disable=<code>,<code>"` to the command:

```toml
[extension-hosts.drupal]
command = ["php", "vendor/amateescu/mago-drupal/resources/worker.php", "--disable=drupal/inline-comment-punctuation"]
```

A rule that is turned off still runs when `mago lint --only` names it. The worker stops with an
error when a code names no rule.

When PHP loads Xdebug, the worker starts again once with `XDEBUG_MODE=off`, because Xdebug slows it
down in any mode. `MAGO_DRUPAL_ALLOW_XDEBUG=1` keeps Xdebug on, for example to step through a rule.

## What it provides

77 linter rules, in groups by what they check. [docs/rules.md](docs/rules.md) describes every rule.
The codes below omit their shared `drupal/` prefix.

- **Bugs and security**: `insecure-unserialize`, `preg-security`, `remote-address`, `weak-hash`.
- **Using the right API**: `deprecation-message`, `discouraged-function`, `global-function`,
  `render-callback`, `symfony-yaml-parse`, `translatable-string`, `translated-exception`,
  `unsilenced-deprecation`.
- **Procedural files**. These rules report only in `.module` and `.install` files:
  `const-prefix`, `constant-prefix`, `empty-install-hook`, `function-prefix`, `global-variable`,
  `install-hook-location`, `t-in-hook-schema`.
- **Files and PHP tags**: `byte-order-mark`, `empty-php-tags`, `file-encoding`,
  `file-start-whitespace`, `short-echo-tag`.
- **Naming and imports**: `class-name-acronym`, `define-name`, `enum-case-name`,
  `fully-qualified-name`, `hook-attribute-name`, `method-name-underscore`, `property-name`,
  `redundant-use`, `use-leading-backslash`.
- **Statements and declarations**: `case-break-blank-line`, `case-fall-through`, `case-semicolon`,
  `comment-in-expression`, `else-if`, `empty-switch`, `method-visibility`, `null-coalesce`,
  `parameter-blank-line`, `property-per-statement`, `property-visibility`, `redundant-return`,
  `short-list`.
- **Comment text**: `author-tag`, `comment-line-length`, `doc-comment-array-syntax`,
  `doc-type-namespace`, `expected-exception-tag`, `gender-neutral-comment`, `inline-comment`,
  `inline-comment-blank-line`, `inline-comment-punctuation`, `long-description-punctuation`,
  `post-statement-comment`, `todo-comment`.
- **Docblock structure**: `class-comment`, `deprecated-tag`, `doc-comment`, `file-comment`,
  `function-comment`, `hook-comment`, `inline-variable-comment`, `variable-comment`.
- **Docblock types**: `nullable-param-tag`.
- **DrupalPractice checks**. Core does not run the sniffs behind these rules: `class-prefix`,
  `curl-ssl-verify`, `form-alter-comment`, `global-constant`, `request-superglobal`,
  `strict-config-schema`, `untranslated-options`.
- **Drupal 7 era**. Core's `phpcs.xml.dist` still enables the matching sniffs, so these rules stay:
  `link-text-translatable`, `t-in-hook-menu`, `watchdog-message`.

The two comment groups port `Drupal.Commenting.*`, whitespace included. The
[parity notes](docs/rules.md#parity-notes) give the details and name what `mago format` covers
instead.

Many rules carry a fix, the way phpcbf fixes Coder's sniffs. `mago lint --fix` applies the safe
ones, and `mago fix` applies them until nothing changes. `--potentially-unsafe` adds the fixes that
turn a comment into a docblock, which the analyzers then read, the ones that drop or move comment or
docblock text, and the `deprecated-tag` rewrite of an old deprecation wording. `--unsafe` adds the
`weak-hash` rewrite, which changes stored digests. Both flags also apply Mago's own fixes of that
level, such as `strict: true` from `strict-behavior`, which can change what a loose comparison
returns. `--dry-run` shows the diff first. [docs/rules.md](docs/rules.md) says which rules fix what.

Rule codes are stable. Projects that we do not control write them into baselines and into
`// @mago-expect lint:<code>` comments. For that reason, the codes get no vendor prefix and no new
name.

The `drupal` analyzer plugin is registered but does not supply providers yet. Every provider
depends on an index of `*.services.yml`, `*.routing.yml`, plugin attributes and `*.schema.yml`.
That index comes first. See the `@todo` list in `src/Analyzer/DrupalPlugin.php`.

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
# The lowest PHP version that the project supports. Mago does not read it from composer.json.
# Without this line, Mago assumes its newest PHP version, and the formatter writes syntax that older
# versions reject, such as new Foo()->bar().
php-version = "8.3"

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
# Report a trait name without the "Trait" suffix, as Coder 9 does.
trait-name = { psr = true }
# Report a file whose name differs from the class that it declares.
file-name = { enabled = true }
# Accept snake_case functions and camelCase methods. With the drupal integration, the rule skips
# the hook documentation in *.api.php files, such as hook_ENTITY_TYPE_insert().
function-name = { either = true }
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

# The no-empty-loop fix deletes a loop whose condition does the work, as in
# while (--$i >= 0 && ...) {}, and Mago marks that fix safe.
no-empty-loop = { enabled = false }

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
