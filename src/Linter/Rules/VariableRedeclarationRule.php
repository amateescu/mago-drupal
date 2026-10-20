<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\FunctionScopes;
use amateescu\MagoDrupal\Internal\VariableScopes;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

use function str_ends_with;

/**
 * Reports a variable that its scope declares again as something else.
 *
 * Ports VariableAnalysis.CodeAnalysis.VariableAnalysis.VariableRedeclaration
 * with the settings of Coder's DrupalPractice standard. The sniff compares
 * the declarations of a name in source order, so it doesn't need flow analysis.
 */
final class VariableRedeclarationRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/variable-redeclaration',
            name: 'Variable redeclaration',
            description: 'Reports a variable that its function declares again, such as a parameter redeclared with static.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            // Program has the whole file in the snapshot. The other kinds
            // put the scopes in the file's target nodes, and their own
            // dispatches don't do anything.
            targets: [NodeKind::Program, ...FunctionScopes::REGIONS],
        );
    }

    public function lint(LintContext $context): void
    {
        // Coder's ruleset skips templates.
        if ($context->node->kind !== NodeKind::Program || str_ends_with($context->file->path, '.tpl.php')) {
            return;
        }

        foreach (VariableScopes::of($context->file, $context->node->id) as $variables) {
            foreach ($variables as $name => $variable) {
                foreach ($variable->redeclarations() as [$span, $before, $after]) {
                    $context->report(Issue::new("Redeclaration of {$before} {$name} as {$after}.", $span)->withHelp(
                        'Give the second declaration another name, or remove it.',
                    ));
                }
            }
        }
    }
}
