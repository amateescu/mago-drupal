# PHPStan identifiers

PHPStan names each error with an identifier, such as `argument.type`. This page lists the
identifiers of PHPStan and phpstan-deprecation-rules that have Mago issues for the same finding.
Search it for an identifier from a baseline, an `ignoreErrors` entry or a `@phpstan-ignore` comment.

The [`phpstan-ignores` plugin](../analyzer/phpstan-ignores.md) reads the same list. With the plugin
on, a comment that ignores one of these identifiers drops the Mago issues next to it on its line.

An identifier that is not listed can still have a Mago issue for the same finding. The list holds
the ones that the plugin matches. The identifiers of phpstan-drupal are on the
[phpstan-drupal](phpstan-drupal.md) page.

<!-- docs-gen:phpstan-identifiers -->

- `argument.type`: `false-argument`, `invalid-argument`, `less-specific-argument`, `mixed-argument`, `null-argument`, `possibly-false-argument`, `possibly-invalid-argument`, `possibly-null-argument`
- `arguments.count`: `too-few-arguments`, `too-many-arguments`
- `assign.propertyType`: `invalid-property-assignment-value`, `mixed-property-type-coercion`, `property-type-coercion`
- `booleanAnd.alwaysFalse`: `impossible-condition`
- `booleanAnd.alwaysTrue`: `redundant-condition`
- `booleanNot.alwaysFalse`: `impossible-condition`
- `booleanNot.alwaysTrue`: `redundant-condition`
- `booleanOr.alwaysFalse`: `impossible-condition`
- `booleanOr.alwaysTrue`: `redundant-condition`
- `class.extendsDeprecatedClass`: `deprecated-class`, `deprecated-trait`
- `class.implementsDeprecatedInterface`: `deprecated-class`, `deprecated-trait`
- `class.notFound`: `non-existent-class`, `non-existent-class-like`
- `classConstant.deprecated`: `deprecated-class-constant`
- `classConstant.deprecatedClass`: `deprecated-class`, `deprecated-trait`
- `classConstant.notFound`: `non-existent-class-constant`
- `constant.deprecated`: `deprecated-constant`
- `constant.notFound`: `non-existent-constant`
- `deadCode.unreachable`: `unevaluated-code`
- `elseif.alwaysFalse`: `impossible-condition`
- `elseif.alwaysTrue`: `redundant-condition`
- `function.deprecated`: `deprecated-function`
- `function.notFound`: `non-existent-function`
- `if.alwaysFalse`: `impossible-condition`
- `if.alwaysTrue`: `redundant-condition`
- `interface.extendsDeprecatedInterface`: `deprecated-class`, `deprecated-trait`
- `method.deprecated`: `deprecated-method`
- `method.deprecatedClass`: `deprecated-class`, `deprecated-trait`
- `method.deprecatedInterface`: `deprecated-class`, `deprecated-trait`
- `method.nonObject`: `ambiguous-object-method-access`, `invalid-method-access`, `method-access-on-null`, `mixed-method-access`, `possible-method-access-on-null`
- `method.notFound`: `non-documented-method`, `non-existent-method`, `possibly-non-existent-method`
- `missingType.parameter`: `missing-parameter-type`
- `missingType.property`: `missing-property-type`
- `missingType.return`: `missing-return-type`
- `new.deprecated`: `deprecated-class`, `deprecated-trait`
- `new.deprecatedClass`: `deprecated-class`, `deprecated-trait`
- `offsetAccess.invalidOffset`: `invalid-array-index`, `mismatched-array-index`, `mixed-array-index`
- `offsetAccess.nonOffsetAccessible`: `false-array-access`, `invalid-array-access`, `mixed-array-access`, `null-array-access`, `possibly-false-array-access`, `possibly-invalid-array-access`, `possibly-null-array-access`
- `offsetAccess.notFound`: `possibly-undefined-array-index`, `possibly-undefined-int-array-index`, `possibly-undefined-string-array-index`, `undefined-int-array-index`, `undefined-string-array-index`
- `property.deprecated`: `deprecated-property`
- `property.deprecatedClass`: `deprecated-class`, `deprecated-trait`
- `property.nonObject`: `ambiguous-object-property-access`, `invalid-property-access`, `mixed-property-access`, `null-property-access`, `possibly-null-property-access`
- `property.notFound`: `non-documented-property`, `non-existent-property`, `possibly-non-existent-property`
- `return.missing`: `missing-return-statement`
- `return.type`: `falsable-return-statement`, `invalid-return-statement`, `less-specific-nested-return-statement`, `less-specific-return-statement`, `mixed-return-statement`, `nullable-return-statement`
- `staticMethod.deprecated`: `deprecated-method`
- `staticMethod.deprecatedClass`: `deprecated-class`, `deprecated-trait`
- `staticMethod.notFound`: `non-existent-method`
- `staticProperty.deprecated`: `deprecated-property`
- `staticProperty.notFound`: `non-existent-property`
- `ternary.alwaysFalse`: `impossible-condition`
- `ternary.alwaysTrue`: `redundant-condition`
- `traitUse.deprecatedTrait`: `deprecated-class`, `deprecated-trait`
- `variable.undefined`: `possibly-undefined-variable`, `undefined-variable`

<!-- /docs-gen -->
