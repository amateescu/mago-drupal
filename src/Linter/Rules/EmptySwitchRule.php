<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\SwitchCases;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

/**
 * Reports a switch that has no `case` label.
 *
 * Ports the MissingCase check of Squiz.ControlStructures.SwitchDeclaration.
 * A `default` label alone does not count. A person has to pick the labels, so
 * there is no fix.
 */
final class EmptySwitchRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/empty-switch',
            name: 'Empty switch',
            description: 'Reports a switch statement that has no case label.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Switch],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        foreach (SwitchCases::labels($file, $context->node) as $label) {
            if ($label->kind === NodeKind::SwitchExpressionCase) {
                return;
            }
        }

        $keyword = $file->getChildren($context->node)[0] ?? $context->node;
        $context->report(Issue::new('A switch must contain at least one case label.', $keyword->span));
    }
}
