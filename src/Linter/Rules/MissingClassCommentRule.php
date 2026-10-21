<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Comment\DeclarationComment;
use amateescu\MagoDrupal\Internal\Syntax\Nodes;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

/**
 * Checks that a class, interface, trait or enum has a docblock.
 *
 * Ports `Missing` of Drupal.Commenting.ClassComment. A file that holds one
 * class often writes its docblock as the file comment instead. That is why
 * a docblock tagged `@file` does not count as the class docblock.
 * `drupal/file-comment` already covers the file comment, and
 * `drupal/class-comment` a comment in the wrong style.
 */
final class MissingClassCommentRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/missing-class-comment',
            name: 'Missing class comment',
            description: 'Reports a class, interface, trait or enum with no docblock.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Class_, NodeKind::Interface, NodeKind::Trait, NodeKind::Enum],
        );
    }

    public function lint(LintContext $context): void
    {
        $comment = DeclarationComment::of($context);
        if ($comment->comment === null || $comment->hasFileTag($context->file)) {
            $keyword = Nodes::CLASS_KEYWORDS[$context->node->kind->name];
            $context->report(Issue::new("The {$keyword} doesn't have a docblock.", $context->node->span));
        }
    }
}
