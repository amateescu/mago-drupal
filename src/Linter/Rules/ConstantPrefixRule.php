<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\ModulePrefix;
use amateescu\MagoDrupal\Internal\Values;
use amateescu\MagoDrupal\Linter\CallRule;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\NodeKind;

/**
 * Reports define() constants in a procedural file that have no module prefix.
 *
 * Ports Drupal.Semantics.ConstantName.ConstantStart. Constants that a module
 * defines share one global namespace. The prefix keeps them apart.
 */
final class ConstantPrefixRule extends CallRule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/constant-prefix',
            name: 'Constant prefix',
            description: 'Reports define() constants that do not start with the module name.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    protected function names(): array
    {
        return ['define'];
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        $expected = ModulePrefix::expected($context->file);
        if ($expected === null) {
            return;
        }

        $name = $this->argument($context, $call, 0);
        if ($name === null || $name->kind !== NodeKind::LiteralString) {
            return;
        }

        $constant = Values::literalString($context->file, $name);
        $issue = $constant === null ? null : ModulePrefix::issue($constant, $expected, $name->span);
        if ($issue !== null) {
            $context->report($issue);
        }
    }
}
