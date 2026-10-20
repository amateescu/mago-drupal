<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;

/**
 * Runs a rule everywhere except in the files of Drupal core itself.
 *
 * It wraps the rules whose checks core's `phpcs.xml.dist` turns off or
 * doesn't run.
 *
 * @internal
 */
final class SkippedInCoreRule implements Rule
{
    public function __construct(
        private readonly Rule $rule,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return $this->rule->getDefinition();
    }

    public function lint(LintContext $context): void
    {
        if (CoreFiles::contains($context->file->path)) {
            return;
        }

        $this->rule->lint($context);
    }
}
