# PHPStan identifiers

PHPStan names each error with an identifier, such as `argument.type`. This page lists the
identifiers of PHPStan and phpstan-deprecation-rules that have Mago issues for the same finding.
Search it for an identifier from a baseline, an `ignoreErrors` entry or a `@phpstan-ignore` comment.

The [`phpstan-ignores` plugin](../analyzer/phpstan-ignores.md) reads the same table. With the plugin
on, a comment that ignores one of these identifiers drops the matching Mago issues on the line that
it [covers](../analyzer/phpstan-ignores.md#comment-forms).

An identifier that isn't listed can still have a Mago issue for the same finding. The table holds
the ones matched by the plugin. The identifiers of phpstan-drupal are on the
[phpstan-drupal](phpstan-drupal.md) page.

<!-- docs-gen:phpstan-identifiers -->

| PHPStan identifier<span class="unbroken-first-column"></span> | Mago issues |
| --- | --- |
| `argument.type` | `false-argument`, `invalid-argument`, `less-specific-argument`, `mixed-argument`, `null-argument`, `possibly-false-argument`, `possibly-invalid-argument`, `possibly-null-argument` |
| `arguments.count` | `too-few-arguments`, `too-many-arguments` |
| `array.invalidKey` | `invalid-array-element-key`, `mixed-array-index` |
| `assign.propertyType` | `invalid-property-assignment-value`, `mixed-property-type-coercion`, `property-type-coercion` |
| `assignOp.invalid` | `invalid-operand`, `mixed-operand`, `possibly-false-operand`, `possibly-invalid-operand`, `possibly-null-operand` |
| `binaryOp.invalid` | `invalid-operand`, `mixed-operand`, `possibly-false-operand`, `possibly-invalid-operand`, `possibly-null-operand` |
| `booleanAnd.alwaysFalse` | `impossible-condition` |
| `booleanAnd.alwaysTrue` | `redundant-condition` |
| `booleanAnd.leftAlwaysFalse` | `impossible-condition`, `redundant-logical-operation` |
| `booleanAnd.leftAlwaysTrue` | `redundant-condition`, `redundant-logical-operation` |
| `booleanAnd.rightAlwaysFalse` | `impossible-condition`, `redundant-logical-operation` |
| `booleanAnd.rightAlwaysTrue` | `redundant-condition`, `redundant-logical-operation` |
| `booleanNot.alwaysFalse` | `impossible-condition` |
| `booleanNot.alwaysTrue` | `redundant-condition` |
| `booleanOr.alwaysFalse` | `impossible-condition` |
| `booleanOr.alwaysTrue` | `redundant-condition` |
| `booleanOr.leftAlwaysFalse` | `impossible-condition`, `redundant-logical-operation` |
| `booleanOr.leftAlwaysTrue` | `redundant-condition`, `redundant-logical-operation` |
| `booleanOr.rightAlwaysFalse` | `impossible-condition`, `redundant-logical-operation` |
| `booleanOr.rightAlwaysTrue` | `redundant-condition`, `redundant-logical-operation` |
| `callable.nonCallable` | `invalid-callable` |
| `class.extendsDeprecatedClass` | `deprecated-class`, `deprecated-trait` |
| `class.implementsDeprecatedInterface` | `deprecated-class`, `deprecated-trait` |
| `class.notFound` | `non-existent-class`, `non-existent-class-like` |
| `classConstant.deprecated` | `deprecated-class-constant` |
| `classConstant.deprecatedClass` | `deprecated-class`, `deprecated-trait`, `deprecated-class-constant` |
| `classConstant.deprecatedEnum` | `deprecated-class`, `deprecated-trait`, `deprecated-class-constant` |
| `classConstant.deprecatedInterface` | `deprecated-class`, `deprecated-trait`, `deprecated-class-constant` |
| `classConstant.notFound` | `non-existent-class-constant` |
| `clone.nonObject` | `invalid-clone`, `mixed-clone`, `possibly-invalid-clone` |
| `constant.deprecated` | `deprecated-constant` |
| `constant.notFound` | `non-existent-constant` |
| `deadCode.unreachable` | `unevaluated-code` |
| `elseif.alwaysFalse` | `impossible-condition` |
| `elseif.alwaysTrue` | `redundant-condition` |
| `empty.property` | `impossible-condition`, `redundant-condition`, `redundant-logical-operation` |
| `empty.variable` | `impossible-condition`, `redundant-condition`, `redundant-logical-operation` |
| `foreach.nonIterable` | `invalid-iterator`, `possibly-null-iterator` |
| `function.alreadyNarrowedType` | `redundant-condition`, `redundant-logical-operation`, `redundant-type-comparison` |
| `function.deprecated` | `deprecated-function` |
| `function.impossibleType` | `impossible-condition`, `impossible-type-comparison` |
| `function.notFound` | `non-existent-function` |
| `identical.alwaysFalse` | `impossible-condition`, `impossible-type-comparison`, `redundant-comparison`, `redundant-condition`, `redundant-logical-operation` |
| `identical.alwaysTrue` | `impossible-condition`, `impossible-type-comparison`, `redundant-comparison`, `redundant-condition`, `redundant-logical-operation` |
| `if.alwaysFalse` | `impossible-condition` |
| `if.alwaysTrue` | `redundant-condition` |
| `instanceof.alwaysFalse` | `impossible-condition`, `impossible-type-comparison`, `redundant-condition`, `redundant-logical-operation` |
| `instanceof.alwaysTrue` | `redundant-condition`, `redundant-logical-operation`, `redundant-type-comparison` |
| `interface.extendsDeprecatedInterface` | `deprecated-class`, `deprecated-trait` |
| `isset.offset` | `impossible-condition`, `redundant-condition`, `redundant-logical-operation` |
| `isset.property` | `impossible-condition`, `redundant-condition`, `redundant-logical-operation` |
| `isset.variable` | `impossible-condition`, `redundant-condition`, `redundant-logical-operation` |
| `match.unhandled` | `match-not-exhaustive` |
| `method.deprecated` | `deprecated-method` |
| `method.deprecatedClass` | `deprecated-class`, `deprecated-trait`, `deprecated-method` |
| `method.deprecatedEnum` | `deprecated-class`, `deprecated-trait`, `deprecated-method` |
| `method.deprecatedInterface` | `deprecated-class`, `deprecated-trait`, `deprecated-method` |
| `method.nonObject` | `ambiguous-object-method-access`, `invalid-method-access`, `method-access-on-null`, `mixed-method-access`, `possible-method-access-on-null` |
| `method.notFound` | `non-documented-method`, `non-existent-method`, `possibly-non-existent-method` |
| `method.staticCall` | `invalid-static-method-access` |
| `missingType.parameter` | `missing-parameter-type` |
| `missingType.property` | `missing-property-type` |
| `missingType.return` | `missing-return-type` |
| `new.deprecated` | `deprecated-class`, `deprecated-trait` |
| `new.deprecatedClass` | `deprecated-class`, `deprecated-trait` |
| `notIdentical.alwaysFalse` | `impossible-condition`, `impossible-type-comparison`, `redundant-comparison`, `redundant-condition`, `redundant-logical-operation` |
| `notIdentical.alwaysTrue` | `impossible-condition`, `impossible-type-comparison`, `redundant-comparison`, `redundant-condition`, `redundant-logical-operation` |
| `nullCoalesce.expr` | `redundant-null-coalesce` |
| `nullCoalesce.offset` | `redundant-null-coalesce` |
| `nullCoalesce.property` | `redundant-null-coalesce` |
| `nullCoalesce.variable` | `redundant-null-coalesce` |
| `nullsafe.neverNull` | `redundant-nullsafe-operator` |
| `offsetAccess.invalidOffset` | `invalid-array-index`, `mismatched-array-index`, `mixed-array-index` |
| `offsetAccess.nonArray` | `invalid-destructuring-source` |
| `offsetAccess.nonOffsetAccessible` | `false-array-access`, `invalid-array-access`, `mixed-array-access`, `null-array-access`, `possibly-false-array-access`, `possibly-invalid-array-access`, `possibly-null-array-access` |
| `offsetAccess.notFound` | `possibly-undefined-array-index`, `possibly-undefined-int-array-index`, `possibly-undefined-string-array-index`, `undefined-int-array-index`, `undefined-string-array-index` |
| `parameter.phpDocType` | `docblock-type-mismatch` |
| `parameterByRef.type` | `reference-constraint-violation` |
| `postDec.type` | `invalid-operand`, `mixed-operand`, `possibly-false-operand`, `possibly-invalid-operand`, `possibly-null-operand` |
| `postInc.type` | `invalid-operand`, `mixed-operand`, `possibly-false-operand`, `possibly-invalid-operand`, `possibly-null-operand` |
| `preDec.type` | `invalid-operand`, `mixed-operand`, `possibly-false-operand`, `possibly-invalid-operand`, `possibly-null-operand` |
| `preInc.type` | `invalid-operand`, `mixed-operand`, `possibly-false-operand`, `possibly-invalid-operand`, `possibly-null-operand` |
| `property.defaultValue` | `invalid-property-default-value` |
| `property.deprecated` | `deprecated-property` |
| `property.deprecatedClass` | `deprecated-class`, `deprecated-trait` |
| `property.nonObject` | `ambiguous-object-property-access`, `invalid-property-access`, `mixed-property-access`, `null-property-access`, `possibly-null-property-access` |
| `property.notFound` | `non-documented-property`, `non-existent-property`, `possibly-non-existent-property` |
| `property.phpDocType` | `docblock-type-mismatch` |
| `return.missing` | `missing-return-statement` |
| `return.phpDocType` | `docblock-type-mismatch` |
| `return.type` | `falsable-return-statement`, `invalid-return-statement`, `less-specific-nested-return-statement`, `less-specific-return-statement`, `mixed-return-statement`, `nullable-return-statement` |
| `staticMethod.deprecated` | `deprecated-method` |
| `staticMethod.deprecatedClass` | `deprecated-class`, `deprecated-trait`, `deprecated-method` |
| `staticMethod.deprecatedEnum` | `deprecated-class`, `deprecated-trait`, `deprecated-method` |
| `staticMethod.deprecatedInterface` | `deprecated-class`, `deprecated-trait`, `deprecated-method` |
| `staticMethod.notFound` | `non-existent-method` |
| `staticProperty.deprecated` | `deprecated-property` |
| `staticProperty.notFound` | `non-existent-property` |
| `ternary.alwaysFalse` | `impossible-condition` |
| `ternary.alwaysTrue` | `redundant-condition` |
| `traitUse.deprecatedTrait` | `deprecated-class`, `deprecated-trait` |
| `unaryOp.invalid` | `invalid-operand`, `mixed-operand`, `possibly-false-operand`, `possibly-invalid-operand`, `possibly-null-operand` |
| `variable.undefined` | `possibly-undefined-variable`, `undefined-variable` |

<!-- /docs-gen -->
