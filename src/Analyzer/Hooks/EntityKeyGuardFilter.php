<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Internal\EntityKeyGuards;
use Mago\Sdk\Analyzer\IssueFilterContext;
use Mago\Sdk\Analyzer\IssueFilterDecision;
use Mago\Sdk\Analyzer\IssueFilterHook;
use Mago\Sdk\Reporting\AnnotationKind;

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
 * @internal
 */
final class EntityKeyGuardFilter implements IssueFilterHook
{
    public function getCodes(): array
    {
        return [
            'falsable-return-statement',
            'invalid-return-statement',
            'possibly-false-argument',
            'possibly-false-operand',
            'mismatched-array-index',
        ];
    }

    public function filterIssue(IssueFilterContext $context): IssueFilterDecision
    {
        foreach ($context->issue->annotations as $annotation) {
            if ($annotation->kind !== AnnotationKind::Primary) {
                continue;
            }

            $span = $annotation->span;
            $guarded =
                ($annotation->file === null || $annotation->file === $context->file)
                && EntityKeyGuards::guarded($context->contents, $span->start, $span->end);

            return $guarded ? IssueFilterDecision::Remove : IssueFilterDecision::Keep;
        }

        return IssueFilterDecision::Keep;
    }
}
