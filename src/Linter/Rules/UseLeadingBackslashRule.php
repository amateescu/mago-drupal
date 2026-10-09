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

/**
 * Reports an import whose first name starts with a backslash.
 *
 * Ports SlevomatCodingStandard.Namespaces.UseDoesNotStartWithBackslash, which
 * Coder 9 runs. A `use` statement always names a class, function or constant
 * from the root, so the backslash adds nothing. Like the sniff, the rule
 * checks only the first name of a statement: the name after `use`,
 * `use function` or `use const`, or the prefix of a group.
 */
final class UseLeadingBackslashRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/use-leading-backslash',
            name: 'Use statement leading backslash',
            description: 'Reports a use statement whose first name starts with a backslash.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Use],
        );
    }

    public function lint(LintContext $context): void
    {
        // The first identifier of the statement is its first name. A
        // `function` or `const` type before it is a keyword, and comments are
        // trivia, so neither gets in the way.
        $name = $context->file->getFirstDescendant($context->node, NodeKind::Identifier);
        if ($name === null || ($context->file->contents[$name->span->start] ?? '') !== '\\') {
            return;
        }

        $span = new Span($name->span->start, $name->span->start + 1);
        $context->report(Issue::new(
            'Do not start an imported name with a backslash.',
            $span,
        )->withEdit(TextEdit::delete($span)));
    }
}
