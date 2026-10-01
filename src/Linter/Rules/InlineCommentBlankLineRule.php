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
 * Checks that no blank line follows a `//` comment on its own line.
 *
 * Ports `SpacingAfter` of Drupal.Commenting.InlineComment, which core's
 * `phpcs.xml.dist` turns off. A blank line between a comment and a closing
 * bracket is the formatter's, which removes it, except before the closing
 * brace of a class-like, where Drupal's style keeps one.
 */
final class InlineCommentBlankLineRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/inline-comment-blank-line',
            name: 'Inline comment blank line',
            description: 'Checks that no blank line follows a `//` comment on its own line.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            // The class-likes are targets so that the Program pass finds their
            // closing braces in the file's target-node list. Their own
            // dispatches do nothing.
            targets: [
                NodeKind::Program,
                NodeKind::Class_,
                NodeKind::Interface,
                NodeKind::Trait,
                NodeKind::Enum,
                NodeKind::AnonymousClass,
            ],
        );
    }

    public function lint(LintContext $context): void
    {
        if ($context->node->kind !== NodeKind::Program) {
            return;
        }

        $closers = [];
        foreach ($context->file->getTargetNodes() as $node) {
            if ($node->kind === NodeKind::Program) {
                continue;
            }

            $closers[$node->span->end - 1] = true;
        }

        InlineCommentSpacing::checkBlankLines($context, $closers);
    }
}
