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
    private string $file = '';

    private string $contents = '';

    private ?DeprecationScopes $scopes = null;

    private string $functionsContents = '';

    private ?NamedFunctions $functions = null;

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
        $scopes = $this->scopes($context);
        if ($scopes !== null && $scopes->covers($span)) {
            return IssueFilterDecision::Remove;
        }

        // A codebase query suspends this request, and another file's request
        // can replace the cached functions in between, so they are passed on
        // rather than read back.
        $functions = $this->functions($context->contents);

        return InheritedDeprecation::covers($context->codebase, $context->file, $functions, $span)
            ? IssueFilterDecision::Remove
            : IssueFilterDecision::Keep;
    }

    /**
     * The scopes of the file the issue is in, or null when it marks none.
     *
     * The bytes are part of the key, not just the path: an editor session
     * analyzes the same path again after every edit, and a path-only key
     * would keep suppressing deprecations in a `@group legacy` class after
     * the marker was deleted. Comparing them costs one memcmp against the
     * file the last issue came from, and only for the handful of codes this
     * hook subscribes to.
     */
    private function scopes(IssueFilterContext $context): ?DeprecationScopes
    {
        if ($this->file === $context->file && $this->contents === $context->contents) {
            return $this->scopes;
        }

        $this->file = $context->file;
        $this->contents = $context->contents;

        return $this->scopes = self::read($context->contents);
    }

    /**
     * The named functions of the file the issue is in, tokenized once for the
     * issues of that file that reach the codebase check.
     */
    private function functions(string $contents): NamedFunctions
    {
        if ($this->functions === null || $this->functionsContents !== $contents) {
            $this->functions = NamedFunctions::of($contents);
            $this->functionsContents = $contents;
        }

        return $this->functions;
    }

    /**
     * The scopes a file's bytes mark, or null when they mark none.
     */
    private static function read(string $contents): ?DeprecationScopes
    {
        return DeprecationScopes::marked($contents) ? DeprecationScopes::of($contents) : null;
    }
}
