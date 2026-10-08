# Coming from PHPStan

These pages show what takes the place of PHPStan, phpstan-drupal and their configuration in a Mago
setup with this extension.

- [phpstan-drupal](phpstan-drupal.md): every rule and extension of phpstan-drupal, and the check,
  rule or analyzer section that takes its place.
- [PHPStan identifiers](identifiers.md): the Mago issues that report the same finding as a PHPStan
  identifier.

## Moving the configuration

| `phpstan.neon` | Mago |
| --- | --- |
| `level` | Mago has no levels. It reports every issue that it finds, at the issue's own level: error, warning, help or note. `minimum-fail-level` under `[analyzer]`, or `--minimum-fail-level`, sets the lowest level that fails the run. |
| Missing types, from level 6 | `check-missing-type-hints = true` under `[analyzer]` reports a missing parameter, return or property type. |
| `paths` | `paths` under `[source]`. |
| `excludePaths` | `excludes` under `[analyzer]`, or under `[source]` to keep a path from every Mago tool. phpstan-drupal leaves out `*.api.php` files and test fixtures by default, and so does the configuration in [Setup](../setup.md#configure-mago-for-drupal). |
| `fileExtensions` | `extensions` under `[source]`. phpstan-drupal adds Drupal's extensions itself, and Mago needs them listed, as in [Install](../setup.md#install). |
| `bootstrapFiles`, `scanFiles`, `scanDirectories` | `includes` under `[source]`: code that Mago reads for its declarations and does not report. Mago runs no code, so a bootstrap file counts only for what it declares. |
| `phpVersion` | `php-version`. |
| `ignoreErrors` | `ignore` under `[analyzer]`, by issue code and optionally by path, as [Turning rules off](../setup.md#turning-rules-off) shows. Mago has nothing like a message pattern. |
| A baseline | A PHPStan baseline does not carry over. `vendor/bin/mago analyze --generate-baseline --baseline=analyzer-baseline.toml` writes a new one, and `baseline = "analyzer-baseline.toml"` under `[analyzer]` reads it. |
| `drupal.drupal_root` | The worker's `--root` argument, see [Finding the Drupal root](../setup.md#finding-the-drupal-root). |
| `drupal.entityMapping` | Nothing. The analyzer reads entity types from their attributes and annotations, see [The entity type index](../analyzer/entities.md#the-entity-type-index). |
| `drupal.rules` | `ignore` under `[analyzer]`, with the code of the check that ports the rule. [phpstan-drupal](phpstan-drupal.md) gives the codes. The worker's `--disable` argument turns off a rule that this extension ports as a linter rule. |

## Extension packages

- phpstan-deprecation-rules: Mago reports deprecated symbols itself. The `drupal` plugin drops the
  reports in the places where Drupal allows a deprecated call, and reports Drupal's deprecations
  that Mago does not. See [Deprecations](../analyzer/deprecations.md).
- phpstan-phpunit: the `phpunit` plugin types PHPUnit's assertions and mocks. See
  [Tests](../analyzer/tests.md).

## Running both

A project can run both tools while it moves. Turn on the
[`phpstan-ignores` plugin](../analyzer/phpstan-ignores.md), so that the `@phpstan-ignore` comments
already in the code drop the Mago issues that report the same finding. Mago's own comment for an
issue that should stay is `@mago-expect analysis:<code>`.
