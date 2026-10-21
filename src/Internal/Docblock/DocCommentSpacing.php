<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Docblock;

use amateescu\MagoDrupal\Internal\Text\LineEnding;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Safety;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;

use function count;
use function strlen;
use function substr;

/**
 * The whitespace checks of Drupal.Commenting.DocComment, for
 * `drupal/doc-comment`: the opener and the closer, blank lines at the ends
 * and around the short description, and the spaces before a description
 * and after a tag. `DocTagGroups` holds the blank lines around the tags.
 *
 * The checks follow Coder's reading of a docblock. A tag is any line whose
 * text starts with `@` and a non-space character, at any indent. A
 * `phpcs:` line is neither text nor a tag.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class DocCommentSpacing
{
    private function __construct() {}

    /**
     * Checks the first and the last line: nothing after the `/**`, and no
     * blank line before the `*\/`. These apply to every docblock, a group's
     * too.
     */
    public static function checkEnds(LintContext $context, Span $span): void
    {
        $rows = DocblockRows::of($context->file, $span);
        $last = count($rows) - 1;
        $lastContent = DocblockRows::previousContent($rows, $last + 1);
        if ($lastContent === null) {
            return;
        }

        $closer = self::closerStart($context->file->contents, $span);
        if ($closer < ($span->end - 2)) {
            $context->report(Issue::new(
                'The docblock must end with "*/", not "'
                . substr($context->file->contents, $closer, $span->end - $closer)
                . '".',
                new Span($closer, $span->end),
            )->withEdit(TextEdit::replace(new Span($closer, $span->end), '*/')->withSafety(Safety::PotentiallyUnsafe)));
        }

        if (!$rows[0]->isBlank()) {
            self::reportOpeningLine($context, $span, $rows[0], oneLine: $last === 0, closer: $closer);
        }

        if ($rows[$last]->isBlank() && $lastContent < ($last - 1)) {
            $context->report(Issue::new(
                'Remove the blank lines at the end of the docblock.',
                new Span($rows[$lastContent + 1]->start, $rows[$last]->start),
            )->withEdit(DocblockRows::noBlankBetween($rows, $lastContent, $last)));
        }
    }

    /**
     * Checks the lines around the short description and the space after
     * each tag. Coder skips these on a docblock that does not start with a
     * description, or with `@file` and one.
     */
    public static function checkBody(LintContext $context, Span $span): void
    {
        $rows = DocblockRows::of($context->file, $span);
        $start = DocblockSummary::start($rows);
        if ($start === null) {
            return;
        }

        [$first, $short] = $start;
        self::checkShortLines($context, $rows, $first, $short);
        $shortEnd = DocblockSummary::shortEnd($rows, $short);
        $long = DocblockRows::nextContent($rows, $shortEnd);
        if ($long === null) {
            return;
        }

        if ($rows[$long]->isProse() && $long !== ($shortEnd + 2)) {
            $issue = Issue::new(
                'Put exactly one blank line between the short and the long description.',
                $rows[$long]->textSpan(),
            );
            $edit = DocblockRows::oneBlankBetween($context->file, $rows, $shortEnd, $long);
            $context->report($edit === null ? $issue : $issue->withEdit($edit));
        }

        $tags = DocblockRows::tags($rows);
        if ($tags === []) {
            return;
        }

        foreach (DocTagGroups::groups($rows, $tags) as $group) {
            self::checkValues($context, $rows, $group);
        }
    }

    /**
     * Where the closer starts. Coder reads the run of stars and slashes at
     * the end of the docblock as the closer, so `**\/` is a closer of its
     * own and not a star and a `*\/`. A normal docblock gives `$end - 2`.
     */
    private static function closerStart(string $contents, Span $span): int
    {
        $start = $span->end - 2;
        while ($start > ($span->start + 3) && ($contents[$start - 1] === '*' || $contents[$start - 1] === '/')) {
            $start--;
        }

        return $start;
    }

    /**
     * Reports text on the line of the `/**`. The fix moves the opener up
     * instead of the text down, so the edits of other issues on the text
     * still apply.
     *
     * @mago-expect lint:no-boolean-flag-parameter
     */
    private static function reportOpeningLine(
        LintContext $context,
        Span $span,
        DocblockRow $row,
        bool $oneLine,
        int $closer,
    ): void {
        $issue = Issue::new('Put the docblock text on the line below the opening /**.', $row->textSpan());
        $indent = DocblockRows::indent($context->file, $span);
        if ($indent === null) {
            $context->report($issue);

            return;
        }

        $eol = LineEnding::of($context->file->contents);
        $issue = $issue->withEdit(TextEdit::replace(new Span($span->start + 3, $row->textStart), "{$eol}{$indent} * "));
        if ($oneLine) {
            $issue = $issue->withEdit(TextEdit::replace(new Span($row->textEnd, $closer), "{$eol}{$indent} "));
        }

        $context->report($issue);
    }

    /**
     * Checks the lines before the short description, or the file
     * description written on the `@file` line.
     *
     * @param list<DocblockRow> $rows
     */
    private static function checkShortLines(LintContext $context, array $rows, int $first, int $short): void
    {
        $row = $rows[$first];
        $isFile = $row->tag() === '@file';
        if ($isFile && $row->value() !== '') {
            self::checkFileLine($context, $row);

            return;
        }

        self::checkShort($context, $rows, $isFile ? $first : 0, $short);
    }

    /**
     * The blank lines before the short description, and the space after its
     * star. A description on the opening line is `checkEnds()`'s.
     *
     * @param list<DocblockRow> $rows
     */
    private static function checkShort(LintContext $context, array $rows, int $start, int $short): void
    {
        $row = $rows[$short];
        if ($short === 0) {
            return;
        }

        if ($short > ($start + 1)) {
            $context->report(Issue::new(
                'Remove the blank lines before the short description.',
                $row->textSpan(),
            )->withEdit(DocblockRows::noBlankBetween($rows, $start, $short)));
        }

        if ($row->star === null) {
            return;
        }

        $space = new Span($row->star + 1, $row->textStart);
        if (substr($context->file->contents, $space->start, $space->length()) !== ' ') {
            $context->report(Issue::new(
                'Put exactly one space between the star and the short description.',
                $row->textSpan(),
            )->withEdit(TextEdit::replace($space, ' ')));
        }
    }

    /**
     * A file description written after `@file` on its line, which goes on
     * the line below.
     */
    private static function checkFileLine(LintContext $context, DocblockRow $row): void
    {
        $valueStart = $row->textEnd - strlen($row->value());
        $issue = Issue::new('Put the file description on the line below @file.', new Span($valueStart, $row->textEnd));
        $prefix = DocblockRows::prefix($context->file, $row);
        $context->report(
            $prefix === null
                ? $issue
                : $issue->withEdit(TextEdit::replace(
                    new Span($row->textStart + strlen('@file'), $valueStart),
                    LineEnding::of($context->file->contents) . $prefix . ' ',
                )),
        );
    }

    /**
     * One space between each tag of a group and the value on its line.
     *
     * @param list<DocblockRow> $rows
     * @param list<int> $group
     */
    private static function checkValues(LintContext $context, array $rows, array $group): void
    {
        foreach ($group as $index) {
            $row = $rows[$index];
            $value = $row->value();
            $space = new Span($row->tagSpan()->end, $row->textEnd - strlen($value));
            if ($value === '' || $space->length() === 1) {
                continue;
            }

            $context->report(Issue::new(
                "Put exactly one space between {$row->tag()} and its value.",
                $space,
            )->withEdit(TextEdit::replace($space, ' ')));
        }
    }
}
