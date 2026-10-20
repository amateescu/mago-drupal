<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;

/**
 * Drops a rule's issues on the lines that a phpcs or PHPStan comment
 * ignores for the checks the rule ports.
 *
 * The linter SDK doesn't have an issue filter, so the rule's issues are
 * dropped here, after the rule runs. An issue counts as on the line where it
 * starts.
 *
 * @internal
 */
final class IgnoredIssueRule implements Rule
{
    public function __construct(
        private readonly Rule $rule,
        private readonly ?PhpcsCommentFilter $phpcs,
        private readonly ?PHPStanCommentFilter $phpstan,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return $this->rule->getDefinition();
    }

    /**
     * The worker empties the context's issues after each rule, so the list
     * holds this rule's issues only.
     */
    public function lint(LintContext $context): void
    {
        $this->rule->lint($context);
        if ($context->issues === []) {
            return;
        }

        $contents = $context->file->contents;
        $kept = [];
        foreach ($context->issues as $issue) {
            if ($this->drops($issue, $contents)) {
                continue;
            }

            $kept[] = $issue;
        }

        $context->issues = $kept;
    }

    private function drops(Issue $issue, string $contents): bool
    {
        // An issue placed in another file can't be under this file's
        // comments.
        $primary = $issue->annotations[0];
        if ($primary->file !== null) {
            return false;
        }

        $offset = $primary->span->start;

        return $this->phpcs?->drops($contents, $offset) === true || $this->phpstan?->drops($contents, $offset) === true;
    }
}
