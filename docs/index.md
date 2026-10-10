# mago-drupal

A [Mago](https://github.com/carthage-software/mago) extension that adds Drupal's coding standard to
the Mago linter. With `mago format` and Mago's own rules, it replaces phpcs and Coder for the PHP
files of a Drupal project.

- **Linter rules.** They port the checks of Coder's `Drupal` and `DrupalPractice` standards
  that know about Drupal, and a few checks of phpstan-drupal. See [Rules](rules/index.md).
- **Fixes.** Many rules have a fix, the way phpcbf fixes Coder's sniffs. `mago lint --fix` applies
  them.
- **A map from Coder.** Every check of Coder's two standards, and what takes its place. Search for
  the code from a phpcs report or a `phpcs:ignore` comment. See
  [Coming from Coder](coder/index.md).

The parity target is [Coder 9](https://www.drupal.org/project/coder/releases/9.0.0).

## Quick start

PHP 8.1 or later and Mago 1.52 or later are required.

```shell
composer require --dev carthage-software/mago amateescu/mago-drupal
```

Register the worker in `mago.toml`, and add Drupal's file extensions:

```toml
[source]
extensions = ["php", "module", "install", "inc", "theme", "profile", "engine", "yml"]

[extension-hosts.drupal]
command = ["php", "vendor/amateescu/mago-drupal/resources/worker.php"]
```

Then run the linter:

```shell
vendor/bin/mago lint
```

[Setup](setup.md) has the formatter and analyzer excludes that go with `yml`, the rest of the
configuration that matches Drupal's standard, the worker's arguments, and the fix levels.

## Analyzer

The extension also registers a `drupal` plugin for `mago analyze`. The plugin has no providers.
Each provider needs an index of `*.services.yml`, `*.routing.yml`, plugin attributes and
`*.schema.yml`, which the extension does not build.
