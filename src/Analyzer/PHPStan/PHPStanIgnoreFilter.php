<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\PHPStan;

use amateescu\MagoDrupal\Internal\Ignore\PHPStanIgnores;
use amateescu\MagoDrupal\Internal\Ignore\UnreachableIgnores;
use Mago\Sdk\Analyzer\IssueFilterContext;
use Mago\Sdk\Analyzer\IssueFilterDecision;
use Mago\Sdk\Analyzer\IssueFilterHook;

use function array_merge;
use function array_unique;
use function array_values;
use function in_array;

/**
 * Drops the Mago issues a `@phpstan-ignore` comment already silences for
 * PHPStan.
 *
 * An identifier drops only the Mago codes that report the same finding, so
 * `@phpstan-ignore return.type` keeps a missing method on the same line.
 * `@phpstan-ignore-line` and `@phpstan-ignore-next-line` drop every code in the
 * table. An identifier the table does not list doesn't drop anything. The issue
 * counts as on the covered line when its first annotation starts there.
 *
 * @internal
 */
final class PHPStanIgnoreFilter implements IssueFilterHook
{
    private const CONDITION_ALWAYS_FALSE = ['impossible-condition'];

    private const CONDITION_ALWAYS_TRUE = ['redundant-condition'];

    /**
     * PHPStan reports an `isset()` or `empty()` whose result is known, and
     * Mago the condition that it makes always true or always false.
     */
    private const CHECK_ALWAYS_KNOWN = ['impossible-condition', 'redundant-condition', 'redundant-logical-operation'];

    /**
     * A type check that can't fail, such as `is_string()` on a string.
     */
    private const TYPE_ALREADY_NARROWED = [
        'impossible-condition',
        'redundant-condition',
        'redundant-logical-operation',
        'redundant-type-comparison',
    ];

    /**
     * A comparison whose result is known. PHPStan names the comparison, and
     * Mago also the condition that it makes always true or always false.
     */
    private const COMPARISON_ALWAYS_KNOWN = [
        'impossible-condition',
        'impossible-type-comparison',
        'redundant-comparison',
        'redundant-condition',
        'redundant-logical-operation',
    ];

    /**
     * One side of an `&&` or `||` that is always true.
     */
    private const OPERAND_ALWAYS_TRUE = ['redundant-condition', 'redundant-logical-operation'];

    /**
     * One side of an `&&` or `||` that is always false.
     */
    private const OPERAND_ALWAYS_FALSE = ['impossible-condition', 'redundant-logical-operation'];

    /**
     * PHPStan has one identifier per operator for an operand of the wrong
     * type, and Mago one code per reason.
     */
    private const OPERAND_TYPE = [
        'invalid-operand',
        'mixed-operand',
        'possibly-false-operand',
        'possibly-invalid-operand',
        'possibly-null-operand',
    ];

    private const DOCBLOCK_TYPE = ['docblock-type-mismatch'];

    private const NULL_COALESCE = ['redundant-null-coalesce'];

    /**
     * A type check that can't pass, such as `is_int()` on a string.
     */
    private const TYPE_NEVER_NARROWED = ['impossible-condition', 'impossible-type-comparison'];

    private const DEPRECATED_CLASS = ['deprecated-class', 'deprecated-trait'];

    /**
     * PHPStan names a deprecated member of a deprecated class-like after the
     * class-like, and Mago after the member.
     */
    private const DEPRECATED_CLASS_CONSTANT = ['deprecated-class', 'deprecated-trait', 'deprecated-class-constant'];

    private const DEPRECATED_CLASS_METHOD = ['deprecated-class', 'deprecated-trait', 'deprecated-method'];

    /**
     * Mago reports every unreachable statement, and `UnreachableIgnores`
     * finds the ones after a covered line.
     */
    private const UNEVALUATED = 'unevaluated-code';

    /**
     * PHPStan identifiers and the Mago codes that report the same finding.
     *
     * @var array<string, non-empty-list<non-empty-string>>
     */
    private const CODES = [
        'argument.type' => [
            'false-argument',
            'invalid-argument',
            'less-specific-argument',
            'less-specific-nested-argument-type',
            'mixed-argument',
            'null-argument',
            'possibly-false-argument',
            'possibly-invalid-argument',
            'possibly-null-argument',
            'template-constraint-violation',
        ],
        'arguments.count' => ['too-few-arguments', 'too-many-arguments'],
        'array.invalidKey' => ['invalid-array-element-key', 'mixed-array-index'],
        'assign.propertyType' => [
            'invalid-property-assignment-value',
            'mixed-property-type-coercion',
            'property-type-coercion',
        ],
        'assignOp.invalid' => self::OPERAND_TYPE,
        'binaryOp.invalid' => self::OPERAND_TYPE,
        'booleanAnd.alwaysFalse' => self::CONDITION_ALWAYS_FALSE,
        'booleanAnd.alwaysTrue' => self::CONDITION_ALWAYS_TRUE,
        'booleanAnd.leftAlwaysFalse' => self::OPERAND_ALWAYS_FALSE,
        'booleanAnd.leftAlwaysTrue' => self::OPERAND_ALWAYS_TRUE,
        'booleanAnd.rightAlwaysFalse' => self::OPERAND_ALWAYS_FALSE,
        'booleanAnd.rightAlwaysTrue' => self::OPERAND_ALWAYS_TRUE,
        'booleanNot.alwaysFalse' => self::CONDITION_ALWAYS_FALSE,
        'booleanNot.alwaysTrue' => self::CONDITION_ALWAYS_TRUE,
        'booleanOr.alwaysFalse' => self::CONDITION_ALWAYS_FALSE,
        'booleanOr.alwaysTrue' => self::CONDITION_ALWAYS_TRUE,
        'booleanOr.leftAlwaysFalse' => self::OPERAND_ALWAYS_FALSE,
        'booleanOr.leftAlwaysTrue' => self::OPERAND_ALWAYS_TRUE,
        'booleanOr.rightAlwaysFalse' => self::OPERAND_ALWAYS_FALSE,
        'booleanOr.rightAlwaysTrue' => self::OPERAND_ALWAYS_TRUE,
        'callable.nonCallable' => ['invalid-callable'],
        'class.extendsDeprecatedClass' => self::DEPRECATED_CLASS,
        'class.implementsDeprecatedInterface' => self::DEPRECATED_CLASS,
        'class.notFound' => ['non-existent-class', 'non-existent-class-like'],
        'classConstant.deprecated' => ['deprecated-class-constant'],
        'classConstant.deprecatedClass' => self::DEPRECATED_CLASS_CONSTANT,
        'classConstant.deprecatedEnum' => self::DEPRECATED_CLASS_CONSTANT,
        'classConstant.deprecatedInterface' => self::DEPRECATED_CLASS_CONSTANT,
        'classConstant.notFound' => ['non-existent-class-constant'],
        'clone.nonObject' => ['invalid-clone', 'mixed-clone', 'possibly-invalid-clone'],
        'constant.deprecated' => ['deprecated-constant'],
        'constant.notFound' => ['non-existent-constant'],
        'deadCode.unreachable' => [self::UNEVALUATED],
        'elseif.alwaysFalse' => self::CONDITION_ALWAYS_FALSE,
        'elseif.alwaysTrue' => self::CONDITION_ALWAYS_TRUE,
        'empty.property' => self::CHECK_ALWAYS_KNOWN,
        'empty.variable' => self::CHECK_ALWAYS_KNOWN,
        'foreach.nonIterable' => ['invalid-iterator', 'possibly-null-iterator'],
        'function.alreadyNarrowedType' => self::TYPE_ALREADY_NARROWED,
        'function.deprecated' => ['deprecated-function'],
        'function.impossibleType' => self::TYPE_NEVER_NARROWED,
        'function.notFound' => ['non-existent-function'],
        'generics.notSubtype' => self::DOCBLOCK_TYPE,
        'identical.alwaysFalse' => self::COMPARISON_ALWAYS_KNOWN,
        'identical.alwaysTrue' => self::COMPARISON_ALWAYS_KNOWN,
        'if.alwaysFalse' => self::CONDITION_ALWAYS_FALSE,
        'if.alwaysTrue' => self::CONDITION_ALWAYS_TRUE,
        'instanceof.alwaysFalse' => [
            'impossible-condition',
            'impossible-type-comparison',
            'redundant-condition',
            'redundant-logical-operation',
        ],
        'instanceof.alwaysTrue' => self::TYPE_ALREADY_NARROWED,
        'interface.extendsDeprecatedInterface' => self::DEPRECATED_CLASS,
        'isset.offset' => self::CHECK_ALWAYS_KNOWN,
        'isset.property' => self::CHECK_ALWAYS_KNOWN,
        'isset.variable' => self::CHECK_ALWAYS_KNOWN,
        'match.unhandled' => ['match-not-exhaustive'],
        'method.alreadyNarrowedType' => self::TYPE_ALREADY_NARROWED,
        'method.deprecated' => ['deprecated-method'],
        'method.deprecatedClass' => self::DEPRECATED_CLASS_METHOD,
        'method.deprecatedEnum' => self::DEPRECATED_CLASS_METHOD,
        'method.deprecatedInterface' => self::DEPRECATED_CLASS_METHOD,
        'method.impossibleType' => self::TYPE_NEVER_NARROWED,
        'method.nonObject' => [
            'ambiguous-object-method-access',
            'invalid-method-access',
            'method-access-on-null',
            'mixed-method-access',
            'possible-method-access-on-null',
        ],
        'method.notFound' => [
            'ambiguous-object-method-access',
            'non-documented-method',
            'non-existent-method',
            'possibly-non-existent-method',
        ],
        'method.staticCall' => ['invalid-static-method-access'],
        'missingType.generics' => ['missing-template-parameter'],
        'missingType.iterableValue' => ['imprecise-type'],
        'missingType.parameter' => ['missing-parameter-type'],
        'missingType.property' => ['missing-property-type'],
        'missingType.return' => ['missing-return-type'],
        'new.deprecated' => self::DEPRECATED_CLASS,
        'new.deprecatedClass' => self::DEPRECATED_CLASS,
        'notIdentical.alwaysFalse' => self::COMPARISON_ALWAYS_KNOWN,
        'notIdentical.alwaysTrue' => self::COMPARISON_ALWAYS_KNOWN,
        'nullCoalesce.expr' => self::NULL_COALESCE,
        'nullCoalesce.offset' => self::NULL_COALESCE,
        'nullCoalesce.property' => self::NULL_COALESCE,
        'nullCoalesce.variable' => self::NULL_COALESCE,
        'nullsafe.neverNull' => ['redundant-nullsafe-operator'],
        'offsetAccess.invalidOffset' => ['invalid-array-index', 'mismatched-array-index', 'mixed-array-index'],
        'offsetAccess.nonArray' => ['invalid-destructuring-source'],
        'offsetAccess.nonOffsetAccessible' => [
            'false-array-access',
            'invalid-array-access',
            'mixed-array-access',
            'mixed-array-assignment',
            'null-array-access',
            'possibly-false-array-access',
            'possibly-invalid-array-access',
            'possibly-null-array-access',
        ],
        'offsetAccess.notFound' => [
            'possibly-null-array-access',
            'possibly-undefined-array-index',
            'possibly-undefined-int-array-index',
            'possibly-undefined-string-array-index',
            'undefined-int-array-index',
            'undefined-string-array-index',
        ],
        'parameter.phpDocType' => self::DOCBLOCK_TYPE,
        'parameterByRef.type' => ['reference-constraint-violation'],
        'postDec.type' => self::OPERAND_TYPE,
        'postInc.type' => self::OPERAND_TYPE,
        'preDec.type' => self::OPERAND_TYPE,
        'preInc.type' => self::OPERAND_TYPE,
        'property.defaultValue' => ['invalid-property-default-value'],
        'property.deprecated' => ['deprecated-property'],
        'property.deprecatedClass' => self::DEPRECATED_CLASS,
        'property.nonObject' => [
            'ambiguous-object-property-access',
            'invalid-property-access',
            'mixed-property-access',
            'null-property-access',
            'possibly-null-property-access',
        ],
        'property.notFound' => [
            'ambiguous-object-property-access',
            'non-documented-property',
            'non-existent-property',
            'possibly-non-existent-property',
        ],
        'property.phpDocType' => self::DOCBLOCK_TYPE,
        'return.empty' => ['invalid-return-statement'],
        'return.missing' => ['missing-return-statement'],
        'return.phpDocType' => self::DOCBLOCK_TYPE,
        'return.type' => [
            'falsable-return-statement',
            'invalid-return-statement',
            'less-specific-nested-return-statement',
            'less-specific-return-statement',
            'mixed-return-statement',
            'nullable-return-statement',
        ],
        'staticMethod.alreadyNarrowedType' => self::TYPE_ALREADY_NARROWED,
        'staticMethod.deprecated' => ['deprecated-method'],
        'staticMethod.deprecatedClass' => self::DEPRECATED_CLASS_METHOD,
        'staticMethod.deprecatedEnum' => self::DEPRECATED_CLASS_METHOD,
        'staticMethod.deprecatedInterface' => self::DEPRECATED_CLASS_METHOD,
        'staticMethod.impossibleType' => self::TYPE_NEVER_NARROWED,
        'staticMethod.notFound' => ['non-existent-method'],
        'staticProperty.deprecated' => ['deprecated-property'],
        'staticProperty.notFound' => ['non-existent-property'],
        'ternary.alwaysFalse' => self::CONDITION_ALWAYS_FALSE,
        'ternary.alwaysTrue' => self::CONDITION_ALWAYS_TRUE,
        'traitUse.deprecatedTrait' => self::DEPRECATED_CLASS,
        'unaryOp.invalid' => self::OPERAND_TYPE,
        'variable.undefined' => ['possibly-undefined-variable', 'undefined-variable'],
    ];

    public function getCodes(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::CODES))));
    }

    public function filterIssue(IssueFilterContext $context): IssueFilterDecision
    {
        $code = $context->issue->code;
        $annotations = $context->issue->annotations;
        $ignores = PHPStanIgnores::of($context->contents);
        if ($code === null || $annotations === [] || $ignores === null) {
            return IssueFilterDecision::Keep;
        }

        $offset = $annotations[0]->span->start;
        if (self::drops($ignores->at($offset), $code)) {
            return IssueFilterDecision::Remove;
        }

        return $code === self::UNEVALUATED && UnreachableIgnores::cover($ignores, $context->contents, $offset)
            ? IssueFilterDecision::Remove
            : IssueFilterDecision::Keep;
    }

    /**
     * Whether what a line ignores drops the code.
     *
     * @param list<string>|bool|null $ignored
     */
    private static function drops(array|bool|null $ignored, string $code): bool
    {
        if ($ignored === null || $ignored === false) {
            return false;
        }

        if ($ignored === true) {
            return true;
        }

        foreach ($ignored as $identifier) {
            if (in_array($code, self::CODES[$identifier] ?? [], strict: true)) {
                return true;
            }
        }

        return false;
    }
}
