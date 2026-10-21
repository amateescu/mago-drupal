<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;

/**
 * Makes the text of a rule's issues valid UTF-8.
 *
 * Mago stops the whole lint run when an issue's text is not valid UTF-8. See
 * Utf8Issue::valid() for what is made valid and how.
 *
 * @internal
 */
final class Utf8IssueRule implements Rule
{
    public function __construct(
        private readonly Rule $rule,
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
        foreach ($context->issues as $index => $issue) {
            $context->issues[$index] = Utf8Issue::valid($issue);
        }
    }
}
