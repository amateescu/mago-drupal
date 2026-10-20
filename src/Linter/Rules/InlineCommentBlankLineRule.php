<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\InlineCommentSpacing;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

/**
 * Checks that a `//` comment on its own line isn't followed by a blank line.
 *
 * Ports `SpacingAfter` of Drupal.Commenting.InlineComment, which core's
 * `phpcs.xml.dist` turns off. A blank line between a comment and a closing
 * bracket is the formatter's. It removes the line before most brackets and
 * adds one before the closing brace of a class-like with members, so the
 * rule skips them all.
 */
final class InlineCommentBlankLineRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/inline-comment-blank-line',
            name: 'Inline comment blank line',
            description: 'Reports a // comment on its own line that a blank line follows.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        InlineCommentSpacing::checkBlankLines($context);
    }
}
