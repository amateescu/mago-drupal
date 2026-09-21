<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;

use function preg_match;
use function strlen;

/**
 * Reports `else if` written as two keywords.
 *
 * Ports Drupal.ControlStructures.ElseIf. `mago format` does not change the
 * pair, so nothing else covers it. An `else` with braces around a nested
 * `if` is a different construct. The rule skips it.
 */
final class ElseIfRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/else-if',
            name: 'Else if',
            description: 'Reports "else if" where Drupal writes "elseif".',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::IfStatementBodyElseClause],
        );
    }

    public function lint(LintContext $context): void
    {
        $matches = [];
        if (preg_match('/^else\s+if\b/i', $context->file->getText($context->node), $matches) !== 1) {
            return;
        }

        $span = new Span($context->node->span->start, $context->node->span->start + strlen($matches[0]));

        $context->report(Issue::new('Use "elseif" instead of "else if".', $span)->withEdit(TextEdit::replace(
            $span,
            text: 'elseif',
        )));
    }
}
