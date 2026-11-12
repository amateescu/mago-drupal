<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Syntax\NodeKind;

/**
 * Reports the `<>` operator, which Drupal's coding standards replace with
 * `!=`.
 *
 * The two spellings are the same operator to PHP, so the fix is safe. Coder
 * doesn't have such a sniff.
 *
 * @internal
 */
final class InequalityOperatorRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/inequality-operator',
            name: 'Inequality operator',
            description: 'Reports the <> operator, which is written != in Drupal.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::BinaryOperator],
        );
    }

    public function lint(LintContext $context): void
    {
        $operator = $context->node;
        if ($context->file->getText($operator) !== '<>') {
            return;
        }

        $context->report(Issue::new('Use "!=" instead of "<>".', $operator->span)->withEdit(TextEdit::replace(
            $operator->span,
            '!=',
        )));
    }
}
