# mago-drupal

mago-drupal is a [Mago](https://github.com/carthage-software/mago) extension that adds
[Drupal's coding standards](https://project.pages.drupalcode.org/coding_standards/) to the Mago
linter, and teaches the Mago analyzer what Drupal's services, entities, config and plugins hand
back. Together with `mago format` and Mago's own rules, it replaces phpcs and Coder for the PHP
files of a Drupal project.

- **Linter rules** check the parts of the standards that Mago's own rules cannot. They port the
  checks of Coder's `Drupal` and `DrupalPractice` standards that need to know about Drupal, add a
  few that only the written standards ask for, and port a few checks of phpstan-drupal. See
  [Linter rules](rules/index.md).
- **Analyzer plugins** give `mago analyze` the types of Drupal's services, entities, config and
  plugins, and port phpstan-drupal's Drupal-specific checks. See [Analyzer](analyzer/index.md).
- **Fixes** work the way phpcbf fixes Coder's sniffs. Many rules have one, and `mago lint --fix`
  applies them.
- **A map from Coder** lists every check of Coder's two standards and what takes its place. Search
  it for the code from a phpcs report or a `phpcs:ignore` comment. See
  [Coming from Coder](coder/index.md).
- **A map from PHPStan** lists every rule and extension of phpstan-drupal and what takes its place,
  and how a `phpstan.neon` moves to Mago. See [Coming from PHPStan](phpstan/index.md).

The rules aim for parity with [Coder 9](https://www.drupal.org/project/coder/releases/9.0.0).

## Quick start

You need PHP 8.1 or later and Mago 1.57 or later.

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

Then run the linter and the analyzer:

```shell
vendor/bin/mago lint
vendor/bin/mago analyze
```

[Setup](setup.md) has the rest: the formatter and analyzer excludes that `yml` needs, the
configuration that matches Drupal's standard, the worker's arguments and the fix levels.
