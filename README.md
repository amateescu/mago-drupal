# mago-drupal

A [Mago](https://github.com/carthage-software/mago) extension that adds
[Drupal's coding standards](https://project.pages.drupalcode.org/coding_standards/) to the Mago
linter, and teaches the Mago analyzer what Drupal's services, entities, config and plugins hand
back. With `mago format` and Mago's own rules, it replaces phpcs and Coder for the PHP files of a
Drupal project. The parity target is
[Coder 9](https://www.drupal.org/project/coder/releases/9.0.0).

## Quick start

PHP 8.1 or later and Mago 1.57 or later are required.

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

[Setup](docs/setup.md) has the formatter and analyzer excludes that go with `yml`. Then run the
linter and the analyzer:

```shell
vendor/bin/mago lint
vendor/bin/mago analyze
```

## Documentation

- [Setup](docs/setup.md): the worker's arguments, the rest of the configuration that matches
  Drupal's standard, and the fix levels.
- [Linter rules](docs/rules/index.md): every rule, with what it reports, what its fix does, the
  section of Drupal's coding standards or the source that it checks, and the Coder checks that it
  ports.
- [Analyzer](docs/analyzer/index.md): what the `drupal`, `phpunit` and `phpstan-ignores` analyzer
  plugins type and report, and their limits.
- [Coming from Coder](docs/coder/index.md): every sniff of Coder's `Drupal` and `DrupalPractice`
  standards, and what takes its place.
- [Coming from PHPStan](docs/phpstan/index.md): every rule and extension of phpstan-drupal, the
  PHPStan identifiers that Mago reports too, and how a `phpstan.neon` moves to Mago.
- [Development](docs/development.md): the checks, the test corpus and building the docs.

## License

MIT.
