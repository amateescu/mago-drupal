<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Comment\CommentAttachment;
use amateescu\MagoDrupal\Internal\Comment\DeclarationComment;
use amateescu\MagoDrupal\Internal\Docblock\DocblockGap;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\TriviaKind;

use function preg_match;

/**
 * Reports a blank line between a docblock and the constant or enum case
 * below it.
 *
 * Drupal's coding standards put a docblock directly before the item it
 * documents. `drupal/function-comment`, `drupal/class-comment` and
 * `drupal/variable-comment` report the same for a function, a class and a
 * property. A docblock with an `@file` tag or a group tag stands on its own.
 * Coder doesn't have such a sniff.
 *
 * @internal
 */
final class ConstantCommentRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/constant-comment',
            name: 'Constant comment',
            description: 'Reports a blank line between a docblock and the constant or enum case below it.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Constant, NodeKind::ClassLikeConstant, NodeKind::EnumCase],
        );
    }

    public function lint(LintContext $context): void
    {
        // The span of a constant starts below its attributes, so the comment
        // is looked for above the first attribute list.
        $file = $context->file;
        $first = $file->getChildren($context->node)[0] ?? null;
        $comment = $first?->kind === NodeKind::AttributeList
            ? CommentAttachment::closest($file, CommentAttachment::anchorAt($context->node, $first))
            : DeclarationComment::of($context)->comment;
        if ($comment === null || $comment->kind !== TriviaKind::DocBlockComment) {
            return;
        }

        $docblock = $comment->span;
        $text = $file->getText($docblock);
        if (preg_match('/@(?:file|defgroup|addtogroup|mainpage)\b|@[{}]/', $text) === 1) {
            return;
        }

        DocblockGap::checkBelow(
            $context,
            $docblock,
            $context->node->kind === NodeKind::EnumCase ? 'enum case' : 'constant',
        );
    }
}
