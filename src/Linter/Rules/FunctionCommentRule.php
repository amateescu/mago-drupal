<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\DeclarationComment;
use amateescu\MagoDrupal\Internal\DocblockGap;
use amateescu\MagoDrupal\Internal\FunctionTags;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

/**
 * Checks the style of the comment above a function or method, and the line
 * between its docblock and the declaration.
 *
 * Ports `WrongStyle` and `SpacingAfter` of Drupal.Commenting.FunctionComment.
 * The `doc-tag-*`, `doc-type-name`, `doc-see-tag` and `doc-sentence` rules
 * check the tags, and `drupal/missing-function-comment` a missing docblock.
 * The comparison of a docblock's types against the real signature is the
 * difficult part of this sniff, and most of it is not ported. `mago analyze`
 * already reads `@param` and `@return` as authoritative types when there is
 * no native hint. It thus already reports a `void` return that returns a
 * value, a function with no `return` at all, an `@param` that names an
 * unknown parameter, and a bare tag with no type.
 */
final class FunctionCommentRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/function-comment',
            name: 'Function comment',
            description: 'Checks the comment style above a function or method, and that its docblock isn\'t followed by a blank line.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Function, NodeKind::Method],
        );
    }

    public function lint(LintContext $context): void
    {
        $comment = DeclarationComment::of($context);
        if ($comment->hasWrongStyle()) {
            $issue = Issue::new('The function docblock must start with "/**".', $context->node->span);
            $fix = $comment->styleEdit($context->file);
            $context->report($fix === null ? $issue : $issue->withEdit($fix));

            return;
        }

        $tags = FunctionTags::of($context);
        if ($tags !== null) {
            DocblockGap::checkBelow($context, $tags->docblock, 'function');
        }
    }
}
