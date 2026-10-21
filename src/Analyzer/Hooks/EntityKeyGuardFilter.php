<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Internal\Entity\EntityKeyGuards;
use amateescu\MagoDrupal\Internal\Type\TypeText;
use Mago\Sdk\Analyzer\IssueFilterContext;
use Mago\Sdk\Analyzer\IssueFilterDecision;
use Mago\Sdk\Analyzer\IssueFilterHook;
use Mago\Sdk\Reporting\AnnotationKind;
use Mago\Sdk\Reporting\ReportedIssue;

use function in_array;
use function preg_match;

/**
 * Drops the `false` reports on an entity type's `getKey()` or
 * `getLinkTemplate()` call that a `hasKey()` or `hasLinkTemplate()` check for
 * the same key guards.
 *
 * Both getters return `string|false`, and Mago doesn't narrow them after the
 * check, since it narrows a variable, not what another call on the same
 * object returns. phpstan-drupal does. Each of these reports has the getter
 * call as its primary span, and EntityKeyGuards reads the code around it.
 *
 * `invalid-return-statement` and `mismatched-array-index` are about the whole
 * `string|false` type, so they are dropped only when a string fits. Their
 * messages name the types that decide it.
 *
 * @internal
 */
final class EntityKeyGuardFilter implements IssueFilterHook
{
    /**
     * The codes that are about the `false` alone.
     */
    private const FALSE_CODES = ['falsable-return-statement', 'possibly-false-argument', 'possibly-false-operand'];

    public function getCodes(): array
    {
        return [...self::FALSE_CODES, 'invalid-return-statement', 'mismatched-array-index'];
    }

    public function filterIssue(IssueFilterContext $context): IssueFilterDecision
    {
        $issue = $context->issue;
        foreach ($issue->annotations as $annotation) {
            if ($annotation->kind !== AnnotationKind::Primary) {
                continue;
            }

            $span = $annotation->span;
            $guarded =
                ($annotation->file === null || $annotation->file === $context->file)
                && self::aboutFalse($issue, $context->contents, $span->end)
                && EntityKeyGuards::guarded($context->contents, $span->start, $span->end);

            return $guarded ? IssueFilterDecision::Remove : IssueFilterDecision::Keep;
        }

        return IssueFilterDecision::Keep;
    }

    /**
     * Whether the issue goes away once the call that ends at byte offset
     * `$end` can't be `false`.
     *
     * A returned string fits a declared type that holds every string. An
     * array write takes any string key, and a read takes one when the key
     * type of the array holds every string.
     */
    private static function aboutFalse(ReportedIssue $issue, string $contents, int $end): bool
    {
        if (in_array($issue->code, self::FALSE_CODES, strict: true)) {
            return true;
        }

        $type = [];
        if ($issue->code === 'invalid-return-statement') {
            return (
                // A literal in the expected type can hold backticks, so the
                // match runs to the found type at the end of the message.
                preg_match('/expected `(.+)`, but found `[^`]*`\.?$/', $issue->message, $type) === 1
                && TypeText::holdsStrings($type[1])
            );
        }

        if (preg_match('/\G\s*\]\s*(?:\?\?)?=(?![=>])/', $contents, offset: $end) === 1) {
            return true;
        }

        foreach ($issue->annotations as $annotation) {
            if (preg_match('/\(type `(.+)` \)/', $annotation->message ?? '', $type) === 1) {
                return TypeText::keysHoldStrings($type[1]);
            }
        }

        return false;
    }
}
