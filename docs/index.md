# mago-drupal

mago-drupal is a [Mago](https://github.com/carthage-software/mago) extension that adds
[Drupal's coding standards](https://project.pages.drupalcode.org/coding_standards/) to the Mago
linter, and teaches the Mago analyzer what Drupal's services, entities, config and plugins hand
back. Together with `mago format` and Mago's own rules, it replaces phpcs and Coder for the PHP
files of a Drupal project.

- **Linter rules** check the parts of the standards that can't be checked by Mago's own rules. They
  port the checks of Coder's `Drupal` and `DrupalPractice` standards that need to know about Drupal,
  add a few asked for only in the written standards, and port a few checks of phpstan-drupal. See
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

You need PHP 8.1 or later and Mago 1.58 or later.

```shell
composer require --dev carthage-software/mago amateescu/mago-drupal
```

Then create `mago.toml` next to `vendor/`:

```toml
extends = "vendor/amateescu/mago-drupal/drupal.mago.toml"
# The lowest PHP version that the project supports.
php-version = "8.3"
```

For a Drupal core checkout, extend `drupal-core.mago.toml` instead. Then run the linter and the
analyzer:

```shell
vendor/bin/mago lint
vendor/bin/mago analyze
```

The next pages have the rest: what the [config files](setup/configuration.md) set, the
[worker's arguments](setup/worker-arguments.md) and the [fix levels](setup/fixes.md).

## Acknowledgements

This extension is based on [Coder](https://www.drupal.org/project/coder) and
[phpstan-drupal](https://github.com/mglaman/phpstan-drupal). The checks of Coder's `Drupal` and
`DrupalPractice` standards are ported to Mago's linter, and the types and rules of phpstan-drupal to
Mago's analyzer.
