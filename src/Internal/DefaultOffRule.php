<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;

/**
 * Registers a rule with its default turned off.
 *
 * Mago runs an extension rule whose default is on unless `--only` picks
 * other rules, and `[linter.rules]` does not take extension codes. A rule
 * that is off by default still runs when `--only` names it.
 *
 * @internal
 */
final class DefaultOffRule implements Rule
{
    public function __construct(
        private readonly Rule $rule,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        $definition = $this->rule->getDefinition();

        return new RuleDefinition(
            code: $definition->code,
            name: $definition->name,
            description: $definition->description,
            defaultLevel: $definition->defaultLevel,
            defaultEnabled: false,
            targets: $definition->targets,
        );
    }

    public function lint(LintContext $context): void
    {
        $this->rule->lint($context);
    }
}
