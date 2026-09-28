<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Internal\DeprecationScopes;
use amateescu\MagoDrupal\Internal\InheritedDeprecation;
use amateescu\MagoDrupal\Internal\NamedFunctions;
use Mago\Sdk\Analyzer\IssueFilterContext;
use Mago\Sdk\Analyzer\IssueFilterDecision;
use Mago\Sdk\Analyzer\IssueFilterHook;

/**
 * Drops deprecation issues where a deprecated call is expected.
 *
 * Drupal marks those places four ways: a `@group legacy` test, a PHPUnit
 * `#[IgnoreDeprecations]` attribute,
 * `DeprecationHelper::backwardsCompatibleCall()`, which runs the deprecated
 * branch on older core, and a `@deprecated` function or class, since
 * deprecated code may use other deprecated code. The scopes are read from the
 * file's own bytes; Mago batches a file's issues into one request, so the
 * file is tokenized once. An issue outside them is also dropped inside a
 * method that overrides a deprecated method, which PHPStan counts as
 * deprecated too; that takes a codebase lookup, and few issues get that far.
 *
 * `deprecated-feature` is left alone: it is about PHP language features, not
 * about the Drupal API a legacy test exercises.
 *
 * @internal
 */
final class DeprecationScopeFilter implements IssueFilterHook
{
    public function getCodes(): array
    {
        return [
            'deprecated-class',
            'deprecated-closure',
            'deprecated-constant',
            'deprecated-function',
            'deprecated-method',
            'deprecated-trait',
        ];
    }

    public function filterIssue(IssueFilterContext $context): IssueFilterDecision
    {
        $annotations = $context->issue->annotations;
        if ($annotations === []) {
            return IssueFilterDecision::Keep;
        }

        // The first annotation is the one the issue points at.
        $span = $annotations[0]->span;
        $contents = $context->contents;
        if (DeprecationScopes::marked($contents) && DeprecationScopes::of($contents)->covers($span)) {
            return IssueFilterDecision::Remove;
        }

        // A codebase query suspends this request, and another file's request
        // can replace the cached functions in between, so they are passed on
        // rather than read back.
        $functions = NamedFunctions::of($contents);

        return InheritedDeprecation::covers($context->codebase, $context->file, $functions, $span)
            ? IssueFilterDecision::Remove
            : IssueFilterDecision::Keep;
    }
}
