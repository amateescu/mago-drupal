<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\PHPStan;

use amateescu\MagoDrupal\Internal\PHPStanIgnores;
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
 * `@phpstan-ignore-line` and `@phpstan-ignore-next-line` drop every code in
 * the table. An identifier the table does not list drops nothing. The issue
 * counts as on the covered line when its first annotation starts there.
 *
 * @internal
 */
final class PHPStanIgnoreFilter implements IssueFilterHook
{
    private const CONDITION_ALWAYS_FALSE = ['impossible-condition'];

    private const CONDITION_ALWAYS_TRUE = ['redundant-condition'];

    private const DEPRECATED_CLASS = ['deprecated-class', 'deprecated-trait'];

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
            'mixed-argument',
            'null-argument',
            'possibly-false-argument',
            'possibly-invalid-argument',
            'possibly-null-argument',
        ],
        'arguments.count' => ['too-few-arguments', 'too-many-arguments'],
        'assign.propertyType' => [
            'invalid-property-assignment-value',
            'mixed-property-type-coercion',
            'property-type-coercion',
        ],
        'booleanAnd.alwaysFalse' => self::CONDITION_ALWAYS_FALSE,
        'booleanAnd.alwaysTrue' => self::CONDITION_ALWAYS_TRUE,
        'booleanNot.alwaysFalse' => self::CONDITION_ALWAYS_FALSE,
        'booleanNot.alwaysTrue' => self::CONDITION_ALWAYS_TRUE,
        'booleanOr.alwaysFalse' => self::CONDITION_ALWAYS_FALSE,
        'booleanOr.alwaysTrue' => self::CONDITION_ALWAYS_TRUE,
        'class.extendsDeprecatedClass' => self::DEPRECATED_CLASS,
        'class.implementsDeprecatedInterface' => self::DEPRECATED_CLASS,
        'class.notFound' => ['non-existent-class', 'non-existent-class-like'],
        'classConstant.deprecated' => ['deprecated-constant'],
        'classConstant.deprecatedClass' => self::DEPRECATED_CLASS,
        'classConstant.notFound' => ['non-existent-class-constant'],
        'constant.deprecated' => ['deprecated-constant'],
        'constant.notFound' => ['non-existent-constant'],
        'elseif.alwaysFalse' => self::CONDITION_ALWAYS_FALSE,
        'elseif.alwaysTrue' => self::CONDITION_ALWAYS_TRUE,
        'function.deprecated' => ['deprecated-function'],
        'function.notFound' => ['non-existent-function'],
        'if.alwaysFalse' => self::CONDITION_ALWAYS_FALSE,
        'if.alwaysTrue' => self::CONDITION_ALWAYS_TRUE,
        'interface.extendsDeprecatedInterface' => self::DEPRECATED_CLASS,
        'method.deprecated' => ['deprecated-method'],
        'method.deprecatedClass' => self::DEPRECATED_CLASS,
        'method.deprecatedInterface' => self::DEPRECATED_CLASS,
        'method.nonObject' => [
            'ambiguous-object-method-access',
            'invalid-method-access',
            'method-access-on-null',
            'mixed-method-access',
            'possible-method-access-on-null',
        ],
        'method.notFound' => ['non-documented-method', 'non-existent-method', 'possibly-non-existent-method'],
        'missingType.parameter' => ['missing-parameter-type'],
        'missingType.property' => ['missing-property-type'],
        'missingType.return' => ['missing-return-type'],
        'new.deprecated' => self::DEPRECATED_CLASS,
        'offsetAccess.invalidOffset' => ['invalid-array-index', 'mismatched-array-index', 'mixed-array-index'],
        'offsetAccess.nonOffsetAccessible' => [
            'false-array-access',
            'invalid-array-access',
            'mixed-array-access',
            'null-array-access',
            'possibly-false-array-access',
            'possibly-invalid-array-access',
            'possibly-null-array-access',
        ],
        'offsetAccess.notFound' => [
            'possibly-undefined-array-index',
            'possibly-undefined-int-array-index',
            'possibly-undefined-string-array-index',
            'undefined-int-array-index',
            'undefined-string-array-index',
        ],
        'property.deprecatedClass' => self::DEPRECATED_CLASS,
        'property.nonObject' => [
            'ambiguous-object-property-access',
            'invalid-property-access',
            'mixed-property-access',
            'null-property-access',
            'possibly-null-property-access',
        ],
        'property.notFound' => ['non-documented-property', 'non-existent-property', 'possibly-non-existent-property'],
        'return.missing' => ['missing-return-statement'],
        'return.type' => [
            'falsable-return-statement',
            'invalid-return-statement',
            'less-specific-nested-return-statement',
            'less-specific-return-statement',
            'mixed-return-statement',
            'nullable-return-statement',
        ],
        'staticMethod.deprecated' => ['deprecated-method'],
        'staticMethod.deprecatedClass' => self::DEPRECATED_CLASS,
        'staticMethod.notFound' => ['non-existent-method'],
        'staticProperty.notFound' => ['non-existent-property'],
        'ternary.alwaysFalse' => self::CONDITION_ALWAYS_FALSE,
        'ternary.alwaysTrue' => self::CONDITION_ALWAYS_TRUE,
        'traitUse.deprecatedTrait' => self::DEPRECATED_CLASS,
        'variable.undefined' => ['possibly-undefined-variable', 'undefined-variable'],
    ];

    private string $contents = '';

    private ?PHPStanIgnores $ignores = null;

    public function getCodes(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::CODES))));
    }

    public function filterIssue(IssueFilterContext $context): IssueFilterDecision
    {
        $code = $context->issue->code;
        $annotations = $context->issue->annotations;
        if ($code === null || $annotations === []) {
            return IssueFilterDecision::Keep;
        }

        $ignored = $this->ignores($context->contents)?->at($annotations[0]->span->start);
        if ($ignored === null) {
            return IssueFilterDecision::Keep;
        }

        if ($ignored === true) {
            return IssueFilterDecision::Remove;
        }

        foreach ($ignored as $identifier) {
            if (in_array($code, self::CODES[$identifier] ?? [], strict: true)) {
                return IssueFilterDecision::Remove;
            }
        }

        return IssueFilterDecision::Keep;
    }

    /**
     * The comments of the file the issue is in, read once for its issues.
     *
     * The bytes are the key, not the path: an editor session analyzes the
     * same path again after every edit.
     */
    private function ignores(string $contents): ?PHPStanIgnores
    {
        if ($this->contents !== $contents) {
            $this->contents = $contents;
            $this->ignores = PHPStanIgnores::of($contents);
        }

        return $this->ignores;
    }
}
