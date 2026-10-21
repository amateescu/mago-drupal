<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Comment\DeclarationComment;
use amateescu\MagoDrupal\Internal\Docblock\DocblockGap;
use amateescu\MagoDrupal\Internal\Docblock\DocblockLine;
use amateescu\MagoDrupal\Internal\Docblock\Docblocks;
use amateescu\MagoDrupal\Internal\Syntax\Nodes;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

use function count;
use function explode;
use function str_contains;
use function strtolower;

/**
 * Checks the comment style above a class, interface, trait or enum, the
 * line between its docblock and the declaration, and its summary.
 *
 * Ports `WrongStyle`, `SpacingAfter` and `Short` of
 * Drupal.Commenting.ClassComment. `drupal/missing-class-comment` reports a
 * declaration with no docblock, or with a docblock tagged `@file`, which
 * belongs to the file. This rule checks neither of those.
 */
final class ClassCommentRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/class-comment',
            name: 'Class comment',
            description: 'Reports a comment of the wrong style above a class, interface, trait or enum, a blank line below its docblock, and a summary that repeats the name.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Class_, NodeKind::Interface, NodeKind::Trait, NodeKind::Enum],
        );
    }

    public function lint(LintContext $context): void
    {
        $keyword = Nodes::CLASS_KEYWORDS[$context->node->kind->name];
        $comment = DeclarationComment::of($context);
        if ($comment->hasWrongStyle()) {
            $issue = Issue::new("The {$keyword} docblock must start with \"/**\".", $context->node->span);
            $fix = $comment->styleEdit($context->file);
            $context->report($fix === null ? $issue : $issue->withEdit($fix));

            return;
        }

        $docblock = $comment->docblock();
        if ($docblock === null || $comment->hasFileTag($context->file)) {
            return;
        }

        DocblockGap::checkBelow($context, $docblock, $keyword);
        $summary = Docblocks::leadingLines($context->file, $docblock);
        $this->checkShort($context, $summary, $keyword);
    }

    /**
     * @param list<DocblockLine> $summary
     */
    private function checkShort(LintContext $context, array $summary, string $keyword): void
    {
        $words = [];
        foreach ($summary as $line) {
            foreach (explode(' ', $line->text) as $word) {
                if ($word === '') {
                    continue;
                }

                $words[] = $word;
            }
        }

        if (count($words) === 0 || count($words) > 2) {
            return;
        }

        $name = Nodes::declaredName($context->file, $context->node);
        if ($name === null) {
            return;
        }

        foreach ($words as $word) {
            if (!str_contains(strtolower($word), strtolower($name))) {
                continue;
            }

            $context->report(Issue::new(
                "The {$keyword} docblock only repeats the {$keyword} name. Describe what the {$keyword} does.",
                $context->node->span,
            ));

            return;
        }
    }
}
