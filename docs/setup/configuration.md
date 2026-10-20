# Configuration

The package has two config files to extend from `mago.toml`, see [Install](install.md):

- `drupal.mago.toml` makes Mago match Drupal's standard, for a site, a module or a theme.
- `drupal-core.mago.toml` adds what core's `phpcs.xml.dist` checks, for a core checkout.

Each setting in the files has a comment that says why it's there.

## What drupal.mago.toml sets

- Drupal's PHP file extensions, and `yml` for the rules for
  [info and routing files](../rules/info-and-routing-files.md). The formatter and the analyzer stay
  out of YAML files, and the linter stays out of config exports.
- The worker, with Xdebug and pcov off, see [Xdebug and pcov](install.md#xdebug-and-pcov).
- Mago's `drupal` formatter preset, and its `drupal` linter integration, which accepts `TRUE`,
  `FALSE` and `NULL`.
- The `Interface` and `Trait` suffixes, and the `file-name` rule, as Coder 9 checks them.
- `check-name-casing` for the analyzer, which ports `Squiz.PHP.LowercasePHPFunctions`. The analyzer
  stays out of `*.api.php` files and test fixtures, as phpstan-drupal does.

It doesn't set `php-version`, since that's up to the project. Without it, Mago assumes its newest
PHP version, and the formatter writes syntax that older versions reject, such as `new Foo()->bar()`.

## Turn off what Drupal's standard doesn't ask for

Mago has many more rules than Drupal's standard. `drupal.mago.toml` turns off the ones that
contradict the standard or repeat a rule of this extension, such as `tagged-todo` and
`function-name`, and the advice that the standard doesn't ask for, such as `no-else-clause` and
`strict-types`. Turning them off doesn't lose any check done by phpcs.

## Changing a setting

The project's `mago.toml` wins over the file that it extends. A table is merged into the one in the
file, so one line changes one rule. An array is added to the one in the file. The worker command is
replaced as a whole.

```toml
extends = "vendor/amateescu/mago-drupal/drupal.mago.toml"
php-version = "8.3"

[source]
# Coder also checks .test files, a Drupal 7 format.
extensions = ["test"]

[linter.rules]
strict-types = { enabled = true }

[analyzer]
# Let @phpstan-ignore comments drop the Mago issues that report the same finding.
plugins = ["phpstan-ignores"]
```

See [PHPStan ignores](../analyzer/phpstan-ignores.md) for the plugin.

## What drupal-core.mago.toml adds

- The files that core's `phpcs.xml.dist` checks: `core/` and `composer/`, with `.test` files, and
  `vendor/` read for its symbols.
- The worker's [`--core` argument](worker-arguments.md#checking-drupal-core).
- The files that phpcs doesn't check, left out of the formatter and the linter: the paths excluded
  by `phpcs.xml.dist`, and the files marked with `// phpcs:ignoreFile` or
  `// @codingStandardsIgnoreFile`, which Mago doesn't read.

## Turning rules off

Mago takes this extension's rule codes under `[linter.rules]`, with the same `enabled`, `level` and
`exclude` settings as its own rules. Quote the code, because it holds a `/`:

```toml
[linter.rules]
"drupal/inline-comment-punctuation" = { enabled = false }
"drupal/missing-function-comment" = { exclude = ["tests/"] }
"drupal/else-if" = { level = "warning" }
```

`enabled = false` turns a rule off. `exclude` leaves the rule out of the matching paths, which take
the same globs and directory prefixes as the excludes of Mago's own rules. `level` sets the level of
every issue that the rule reports. `enabled = true` turns on a rule that the worker's
[`--core` argument](worker-arguments.md#checking-drupal-core) turns off.

A rule that is turned off still runs when `mago lint --only` names it, and `mago lint --pedantic`
doesn't read these settings. Mago warns about a code that doesn't name a rule, and the run goes on.

A project that turns phpcs sub-codes off in its `phpcs.xml` can turn off the matching rules this
way. [Coming from Coder](../coder/index.md) says which rule ports each sub-code.

`[linter.rules]` takes only linter rules. To turn off an [analyzer check](../analyzer/index.md#checks),
use Mago's own `[analyzer] ignore`, for the whole project or for some paths:

```toml
[analyzer]
ignore = [
    "drupal/global-drupal-call",
    { code = "drupal/entity-storage-injection", in = "src/Legacy/" },
]
```
