<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\DocblockLine;
use amateescu\MagoDrupal\Internal\DocblockRows;
use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\DocblockTag;
use amateescu\MagoDrupal\Internal\DocCommentSpacing;
use amateescu\MagoDrupal\Internal\DocDoubleDot;
use amateescu\MagoDrupal\Internal\DocStarSpacing;
use amateescu\MagoDrupal\Internal\OuterDocblocks;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;

use function basename;
use function count;
use function in_array;
use function mb_substr;
use function ord;
use function preg_match;
use function rtrim;
use function strlen;
use function strspn;
use function strtolower;
use function strtoupper;

/**
 * Checks a docblock's short description, long description and tag order.
 *
 * Ports Drupal.Commenting.DocComment. `DocCommentSpacing` holds the checks
 * on blank lines, on the closer, and on the spaces before a description and
 * after a tag. `DocStarSpacing` ports the space after the star of
 * Drupal.Commenting.DocCommentAlignment, and `DocDoubleDot` ports the
 * two-dot pattern of SlevomatCodingStandard.Commenting.ForbiddenComments.
 * Both of those run on every docblock, inside function bodies too. The end
 * of the long description is `drupal/long-description-punctuation`'s, since
 * core's `phpcs.xml.dist` turns that check off. A `phpcs:` line inside the
 * docblock is not part of a description, as Coder reads it. The description
 * of a file docblock is the text after its `@file` tag.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class DocCommentRule implements Rule
{
    /**
     * Tags that this rule checks for order. Each must also be in one group.
     */
    private const ORDERED_TAGS = ['param', 'return', 'throws'];

    public function getDefinition(): RuleDefinition
    {
        // Function and Method are targets for `OuterDocblocks::of()`. Their
        // own dispatches do nothing.
        return new RuleDefinition(
            code: 'drupal/doc-comment',
            name: 'Doc comment',
            description: "Checks a docblock's short description, long description and tag order.",
            defaultLevel: Level::Warning,
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
        $summaryRow = $isGroup ? null : DocCommentSpacing::summaryRow(DocblockRows::of($context->file, $span));
        DocStarSpacing::check($context, $span, $summaryRow);
        if ($isGroup) {
            return;
        }

        DocCommentSpacing::checkBody($context, $span);

        $tags = Docblocks::tags($context->file, $span);
        [$summary, $description] = Docblocks::paragraphs($context->file, $span);

        if ($summary === [] && $tags === []) {
            $context->report(Issue::new('The docblock is empty.', $span));

            return;
        }

        if ($summary === []) {
            $this->checkNoSummary($context, $span, $tags);
        }

        if ($summary !== []) {
            $this->checkShortCapital($context, $summary);
            $this->checkSummaryEnd($context, $summary);
            if (count($summary) > 1) {
                $context->report(Issue::new(
                    'A short description must fit on one line. Move the rest to a long description.',
                    $this->lastLineSpan($summary),
                ));
            }
        }

        // Coder reports only a lower-case letter at the start of the long
        // description.
        if ($description !== [] && self::lowerCaseStart($description[0]) !== null) {
            self::reportCapital($context, $description[0], 'long description');
        }

        $this->checkTagOrder($context, $tags);
    }

    /**
     * Checks a docblock with no short description from its first tag, or
     * from the tag after a leading `@file`, as Coder does.
     *
     * `@covers` there needs no summary, as PHPUnit test methods often have
     * none. A bare `@inheritdoc` there gets the braces report alone. Coder
     * reads no other tag, so `@covers` followed by `@group` is fine, and an
     * `@inheritdoc` further down is not reported. `OuterDocblocks::isGroup()`
     * skips the group markers and `@coversDefaultClass`.
     *
     * @param list<DocblockTag> $tags
     */
    private function checkNoSummary(LintContext $context, Span $span, array $tags): void
    {
        $tag = $tags[0];
        if ($context->file->getText($tag->nameSpan) === '@file') {
            // A docblock with `@file` alone is fine. Coder reports it, but
            // core has many of those and runs `MissingShort` only in tests.
            if (count($tags) === 1) {
                return;
            }

            $tag = $tags[1];
        }

        if ($tag->name === 'inheritdoc') {
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

            return;
        }

        if ($context->file->getText($tag->nameSpan) !== '@covers') {
            $context->report(Issue::new('The docblock has no short description.', $span));
        }
    }

    /**
     * Checks that the short description starts with an upper-case letter.
     *
     * Coder tests the first byte of the summary. A digit, `#`, `_` or any
     * other ASCII character that is not an upper-case letter is reported. A
     * multi-byte first character is not: Coder's Unicode pattern errors on a
     * lone byte of it, so the check never fires. The `{@inheritdoc}` tag
     * alone, and a summary that is the file's own name, as Features exports
     * write, are fine.
     *
     * @param list<DocblockLine> $summary
     */
    private function checkShortCapital(LintContext $context, array $summary): void
    {
        $content = Docblocks::joinedText($summary);
        if ($content === '') {
            return;
        }

        $first = ord($content[0]);
        if (
            $first >= 0x41 && $first <= 0x5A
            || $first >= 0x80
            || $content === '{@inheritdoc}'
            || $content === '{@inheritDoc}'
            || $content === basename($context->file->path)
        ) {
            return;
        }

        self::reportCapital($context, $summary[0], 'short description');
    }

    /**
     * Reports a paragraph whose first character is not a capital letter.
     * Like phpcbf, the fix uppercases a lower-case letter. Any other first
     * character gets no fix.
     */
    private static function reportCapital(LintContext $context, DocblockLine $line, string $label): void
    {
        $start = $line->offset + strspn($line->text, characters: " \t");
        $issue = Issue::new("The {$label} must start with a capital letter.", new Span($start, $start + 1));
        $lower = self::lowerCaseStart($line);
        if ($lower !== null) {
            $issue = $issue->withEdit(TextEdit::replace(new Span($start, $start + 1), strtoupper($lower)));
        }

        $context->report($issue);
    }

    /**
     * Returns the first character of a line's text when it is a lower-case
     * ASCII letter, the only kind that PHP's ucfirst() changes, or null.
     */
    private static function lowerCaseStart(DocblockLine $line): ?string
    {
        $matches = [];

        return preg_match('/^[ \t]*([a-z])/', $line->text, $matches) === 1 ? $matches[1] : null;
    }

    /**
     * Checks that the short description ends in one of a fixed set of
     * terminal marks. `drupal/long-description-punctuation` checks the long
     * description, more loosely.
     *
     * @param list<DocblockLine> $summary
     */
    private function checkSummaryEnd(LintContext $context, array $summary): void
    {
        $last = $summary[count($summary) - 1];
        // The text is trimmed first. Otherwise a stray trailing space, or a
        // "\r" that the line splitter keeps under CRLF, makes it look
        // unexempt.
        $trimmed = rtrim($last->text);
        $lastChar = mb_substr($trimmed, -1);
        // Coder accepts `{@inheritDoc}` too, and a summary that is the
        // file's own name.
        if (
            strtolower($trimmed) === '{@inheritdoc}'
            || in_array($lastChar, ['.', '!', '?', ')'], strict: true)
            || Docblocks::joinedText($summary) === basename($context->file->path)
        ) {
            return;
        }

        $lastCharEnd = $last->offset + strlen($trimmed);
        $issue = Issue::new(
            'The short description must end with terminal punctuation.',
            new Span($lastCharEnd - strlen($lastChar), $lastCharEnd),
        );

        // Like phpcbf, the fix adds a full stop only after a letter or a
        // digit on a one-line summary. A summary over two lines may be
        // missing the blank line before the long description.
        if (count($summary) > 1 || preg_match('/^[a-zA-Z0-9]$/', $lastChar) !== 1) {
            $context->report($issue);

            return;
        }

        $context->report($issue->withEdit(TextEdit::insert($lastCharEnd, '.')));
    }

    /**
     * The span of the last line of a paragraph, where Coder reports it.
     *
     * @param list<DocblockLine> $paragraph
     */
    private function lastLineSpan(array $paragraph): Span
    {
        $last = $paragraph[count($paragraph) - 1];

        return new Span($last->offset, $last->offset + strlen(rtrim($last->text)));
    }

    /**
     * Reports a tag name that appears again after a different ordered tag.
     * `DocCommentSpacing` reports the `@param` groups.
     *
     * Only `@param`, `@return` and `@throws` are ordered tags. No other tag
     * counts here, and that includes Doxygen markup such as `@code`,
     * `@endcode`, `@todo` and `@link`. Such a tag does not have to be in a
     * group, and it does not split one. Coder compares with the last ordered
     * tag only.
     *
     * @param list<DocblockTag> $tags
     */
    private function checkTagOrder(LintContext $context, array $tags): void
    {
        $seen = [];
        $lastName = null;
        foreach ($tags as $tag) {
            if (!in_array($tag->name, self::ORDERED_TAGS, strict: true)) {
                continue;
            }

            if (($seen[$tag->name] ?? false) && $lastName !== $tag->name) {
                $context->report(Issue::new(
                    "Keep the @{$tag->name} tags together. Do not put other tags between them.",
                    $tag->nameSpan,
                ));
            }

            $seen[$tag->name] = true;
            $lastName = $tag->name;
        }
    }
}
