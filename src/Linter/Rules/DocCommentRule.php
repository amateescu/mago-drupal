<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Docblock\DocblockRows;
use amateescu\MagoDrupal\Internal\Docblock\Docblocks;
use amateescu\MagoDrupal\Internal\Docblock\DocblockSummary;
use amateescu\MagoDrupal\Internal\Docblock\DocCommentSpacing;
use amateescu\MagoDrupal\Internal\Docblock\DocDoubleDot;
use amateescu\MagoDrupal\Internal\Docblock\DocStarSpacing;
use amateescu\MagoDrupal\Internal\Docblock\OuterDocblocks;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;

/**
 * Checks the whitespace and the edges of a docblock, and `{@inheritdoc}`
 * written without braces.
 *
 * Ports the whitespace codes of Drupal.Commenting.DocComment.
 * `DocCommentSpacing` holds the checks on blank lines, on the opener and
 * the closer, and on the spaces before a description and after a tag.
 * `DocStarSpacing` ports the space after the star of
 * Drupal.Commenting.DocCommentAlignment, and `DocDoubleDot` ports the
 * two-dot pattern of SlevomatCodingStandard.Commenting.ForbiddenComments.
 * Both of those run on every docblock, inside function bodies too. The
 * summary, the tags and their groups have rules of their own.
 */
final class DocCommentRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        // Function and Method are targets for `OuterDocblocks::of()`. Their
        // own dispatches don't do anything.
        return new RuleDefinition(
            code: 'drupal/doc-comment',
            name: 'Doc comment',
            description: 'Reports a docblock with a wrong opener, closer, blank lines or spaces, or with @inheritdoc without braces.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program, NodeKind::Function, NodeKind::Method],
        );
    }

    public function lint(LintContext $context): void
    {
        if ($context->node->kind !== NodeKind::Program) {
            return;
        }

        foreach (OuterDocblocks::of($context->file) as $span) {
            $this->checkDocblock($context, $span);
        }

        // Coder checks the stars and the end of a description line in every
        // docblock, the ones inside a function body too.
        foreach (OuterDocblocks::inBodies($context->file) as $span) {
            DocStarSpacing::check($context, $span, summaryRow: null);
            DocDoubleDot::check($context, $span);
        }
    }

    private function checkDocblock(LintContext $context, Span $span): void
    {
        DocCommentSpacing::checkEnds($context, $span);
        DocDoubleDot::check($context, $span);
        $isGroup = OuterDocblocks::isGroup($context->file, $span);
        $summaryRow = $isGroup ? null : DocblockSummary::summaryRow(DocblockRows::of($context->file, $span));
        DocStarSpacing::check($context, $span, $summaryRow);
        if ($isGroup) {
            return;
        }

        DocCommentSpacing::checkBody($context, $span);

        $tags = Docblocks::tags($context->file, $span);
        if ($tags === [] || Docblocks::paragraphs($context->file, $span)[0] !== []) {
            return;
        }

        // With no summary, Coder reads the first tag, or the one after a
        // leading `@file`. A bare `@inheritdoc` there gets this report, and
        // no report of the missing summary.
        $tag = DocblockSummary::leadTag($context->file, $tags);
        if ($tag === null || $tag->name !== 'inheritdoc') {
            return;
        }

        // A stray closing brace, as in `@inheritdoc}`, goes into the
        // replacement too, so the result does not end in two of them.
        $replaced = $tag->nameSpan;
        if (($context->file->contents[$replaced->end] ?? '') === '}') {
            $replaced = new Span($replaced->start, $replaced->end + 1);
        }

        $context->report(Issue::new(
            'Write @inheritdoc as {@inheritdoc}, with curly braces, to make it an inline tag.',
            $tag->nameSpan,
        )->withEdit(TextEdit::replace($replaced, '{@inheritdoc}')));
    }
}
