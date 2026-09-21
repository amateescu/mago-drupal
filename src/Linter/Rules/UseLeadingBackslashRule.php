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
 * Reports an import whose name starts with a backslash.
 *
 * Ports Drupal.Classes.UseLeadingBackslash. A `use` statement always names a
 * class from the root, so the backslash adds nothing.
 *
 * The check reads the bytes directly after the `use` keyword. The ported
 * sniff does the same, and that is why `use function \foo;` passes. The rule
 * covers only a class import, and only the first name in a multi-name
 * statement.
 */
final class UseLeadingBackslashRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/use-leading-backslash',
            name: 'Use statement leading backslash',
            description: 'Reports a use statement that imports a class with a leading backslash.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Use],
        );
    }

    public function lint(LintContext $context): void
    {
        $matches = [];
        if (preg_match('/^use\s+\\\\/i', $context->file->getText($context->node), $matches) !== 1) {
            return;
        }

        // The match ends on the backslash itself. The fix removes that byte.
        $offset = $context->node->span->start + strlen($matches[0]) - 1;
        $span = new Span($offset, $offset + 1);

        $context->report(Issue::new(
            'Do not start an imported class name with a backslash.',
            $span,
        )->withEdit(TextEdit::delete($span)));
    }
}
