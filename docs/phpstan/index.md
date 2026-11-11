# Coming from PHPStan

These pages show what takes the place of PHPStan, phpstan-drupal and their configuration in a Mago
setup with this extension.

- [phpstan-drupal](phpstan-drupal.md): every rule and extension of phpstan-drupal, and the check,
  rule or analyzer section that takes its place.
- [PHPStan identifiers](identifiers.md): the Mago issues that report the same finding as a PHPStan
  identifier.

## Moving the configuration

| `phpstan.neon`<span class="unbroken-first-column"></span> | Mago |
| --- | --- |
| `level` | Levels don't exist in Mago. It reports every issue found, at the issue's own level: error, warning, help or note. `minimum-fail-level` under `[analyzer]`, or `--minimum-fail-level`, sets the lowest level that fails the run. [Starting from a PHPStan level](#starting-from-a-phpstan-level) lists the issue codes by the PHPStan level that reports the same findings. |
| Missing types, from level 6 | `check-missing-type-hints = true` under `[analyzer]` reports a missing parameter, return or property type. |
| `paths` | `paths` under `[source]`. |
| `excludePaths` | `excludes` under `[analyzer]`, or under `[source]` to keep a path from every Mago tool. phpstan-drupal leaves out `*.api.php` files and test fixtures by default, and so does the [configuration](../setup/configuration.md). |
| `fileExtensions` | `extensions` under `[source]`. phpstan-drupal adds Drupal's extensions itself, and Mago needs them listed, as in [Install](../setup/install.md). |
| `bootstrapFiles`,<br>`scanFiles`,<br>`scanDirectories` | `includes` under `[source]`: code read by Mago for its declarations and not reported. Mago doesn't run any code, so a bootstrap file counts only for what it declares. |
| `phpVersion` | `php-version`. |
| `ignoreErrors` | `ignore` under `[analyzer]`, by issue code, by path, or by a `pattern` that the text of the issue must match, as [Turning rules off](../setup/configuration.md#turning-rules-off) shows. |
| A baseline | A PHPStan baseline can't be reused. `vendor/bin/mago analyze --generate-baseline --baseline=analyzer-baseline.toml` writes a new one, and `baseline = "analyzer-baseline.toml"` under `[analyzer]` reads it. |
| `drupal.drupal_root` | The worker's `--root` argument, see [Finding the Drupal root](../setup/worker-arguments.md#finding-the-drupal-root). |
| `drupal.entityMapping` | Nothing. The analyzer reads entity types from their attributes and annotations, see [The entity type index](../analyzer/entities.md#the-entity-type-index). |
| `drupal.rules` | `ignore` under `[analyzer]`, with the code of the check that ports the rule. [phpstan-drupal](phpstan-drupal.md) gives the codes. `enabled = false` under `[linter.rules]` turns off a rule ported by this extension as a linter rule. |

## Starting from a PHPStan level

Mago doesn't have levels, so a project that runs PHPStan at level 6 also gets the issues that
PHPStan reports from level 7 on. The table lists Mago's issue codes under the PHPStan level that
reports the same findings. To leave out the levels above the project's own, add the codes of
their rows to `ignore` under `[analyzer]`, see
[Turning rules off](../setup/configuration.md#turning-rules-off). For level 6, those are the rows
for 7, 8, and 9 and 10. The last row has most of the issues.

| PHPStan level | Mago codes |
| --- | --- |
| 2 | `non-existent-method`, `non-existent-property`, `non-documented-property`, `invalid-property-access`, `too-few-arguments`, `too-many-arguments`, `docblock-type-mismatch` |
| 3 | `property-type-coercion`, `less-specific-return-statement`, `invalid-property-default-value` |
| 4 | `redundant-logical-operation`, `redundant-comparison`, `redundant-type-comparison`, `impossible-type-comparison`, `invalid-operand`, `impossible-condition`, `redundant-condition` |
| 5 | `invalid-argument`, `null-argument` |
| 7 | `possibly-invalid-argument`, `possibly-false-argument`, `possibly-invalid-array-access`, `possibly-false-array-access`, `invalid-array-access`, `invalid-method-access`, `invalid-return-statement`, `falsable-return-statement`, `invalid-property-assignment-value`, `unknown-class-instantiation` |
| 8 | `possible-method-access-on-null`, `possibly-null-argument`, `possibly-null-property-access`, `possibly-null-iterator`, `nullable-return-statement`, `reference-constraint-violation`, `invalid-member-selector` |
| 9 and 10 | `mixed-argument`, `mixed-array-access`, `mixed-array-assignment`, `mixed-array-index`, `mixed-method-access`, `mixed-operand`, `mixed-property-access`, `mixed-property-type-coercion`, `mixed-return-statement`, `less-specific-nested-argument-type`, `less-specific-nested-return-statement`, `invalid-iterator`, `invalid-destructuring-source`, `invalid-type-cast`, `possibly-null-array-index`, `array-to-string-conversion`, `string-member-selector`, `template-constraint-violation` |

- Levels 0, 1 and 6 aren't listed. Level 6 adds missing types, which are
  `check-missing-type-hints` in Mago, and no code belongs to level 0 or 1.
- Level 9 is for a value documented as `mixed`, and level 10 for a value without a type. Mago has
  the same codes for both.
- PHPStan reports part of `invalid-method-access` at level 2, and part of
  `invalid-return-statement` and `invalid-property-assignment-value` at level 3. The rest comes at
  levels 7 and 8, with the checks on each member of a union type.
- PHPStan reports only part of `impossible-condition` and `redundant-condition`.

These codes don't belong to a level. PHPStan reports few of their findings, or none:
`mixed-assignment`, `possibly-null-operand`, `less-specific-argument`, `implicit-to-string-cast`,
`possibly-null-array-access`, `undefined-variable`, `incompatible-parameter-name`,
`ambiguous-object-method-access`, `ambiguous-object-property-access` and
`redundant-docblock-type`.

## Extension packages

- phpstan-deprecation-rules: Mago reports deprecated symbols itself. The `drupal` plugin drops the
  reports in the places where a deprecated call is allowed in Drupal, and reports Drupal's
  deprecations not reported by Mago. See [Deprecations](../analyzer/deprecations.md).
- phpstan-phpunit: the `phpunit` plugin types PHPUnit's assertions and mocks. See
  [Tests](../analyzer/tests.md).

## Running both

A project can run both tools while it moves. Turn on the
[`phpstan-ignores` plugin](../analyzer/phpstan-ignores.md), so that the `@phpstan-ignore` comments
already in the code drop the Mago issues that report the same finding. Mago's own comment for an
issue that should stay is `@mago-expect analysis:<code>`.
