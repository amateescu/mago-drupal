<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Safety;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;

use function array_key_exists;
use function array_key_last;
use function count;
use function in_array;
use function strlen;
use function strtolower;
use function substr;

/**
 * The whitespace checks of Drupal.Commenting.DocComment, for
 * `drupal/doc-comment`: blank lines around the descriptions and the tags,
 * and the spaces before a description and after a tag.
 *
 * The checks follow Coder's reading of a docblock. A tag is any line whose
 * text starts with `@` and a non-space character, at any indent. A
 * `phpcs:` line is neither text nor a tag. Only the tags at the column of
 * the first one form groups, so a tag indented into a description is not
 * one of them.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class DocCommentSpacing
{
    /**
     * The tags whose sections need a blank line before and after them.
     */
    private const SECTION_TAGS = ['@param', '@return', '@throws'];

    /**
     * First tags that may follow the description with no blank line.
     */
    private const INLINE_TAGS = ['@code', '@link', '@endlink'];

    /**
     * First tags that let the `@param` group come later. They are markup,
     * or a note, and do not count as a group of tags.
     */
    private const PARAM_LEADING_TAGS = ['@code', '@todo', '@link', '@endlink', '@codingstandardsignorestart'];

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
     * Checks the lines between and inside the descriptions and the tags.
     * Coder skips these on a docblock that does not start with a
     * description, or with `@file` and one.
     */
    public static function checkBody(LintContext $context, Span $span): void
    {
        $rows = DocblockRows::of($context->file, $span);
        $first = DocblockRows::nextContent($rows, -1);
        $short = $first === null ? null : self::shortDescription($context, $rows, $first);
        if ($first === null || $short === null) {
            return;
        }

        $shortEnd = $short;
        while (array_key_exists($shortEnd + 1, $rows) && $rows[$shortEnd + 1]->isProse()) {
            $shortEnd++;
        }

        $long = DocblockRows::nextContent($rows, $shortEnd);
        if ($long === null) {
            return;
        }

        if ($rows[$long]->isProse() && $long !== ($shortEnd + 2)) {
            self::report(
                $context,
                Issue::new(
                    'Put exactly one blank line between the short and the long description.',
                    $rows[$long]->textSpan(),
                ),
                DocblockRows::oneBlankBetween($context->file, $rows, $shortEnd, $long),
            );
        }

        $tags = DocblockRows::tags($rows);
        if ($tags === []) {
            return;
        }

        if ($rows[$first]->tag() !== '@file') {
            self::checkBeforeTags($context, $rows, $tags[0]);
        }

        $groups = self::groups($context, $rows, $tags);
        self::checkParams($context, $rows, $groups);
        foreach ($groups as $group) {
            self::checkValues($context, $rows, $group);
            self::checkAfterGroup($context, $rows, $group, $rows[$tags[0]]->column());
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
     * The line that holds the short description, which the check of the
     * space after its star covers, or null when the docblock has none.
     *
     * @param list<DocblockRow> $rows
     */
    public static function summaryRow(array $rows): ?int
    {
        $first = DocblockRows::nextContent($rows, -1);
        if ($first === null) {
            return null;
        }

        $isFile = $rows[$first]->tag() === '@file';
        if ($isFile && $rows[$first]->value() !== '') {
            return null;
        }

        $short = $isFile ? DocblockRows::nextContent($rows, $first) : $first;

        return $short !== null && $rows[$short]->isProse() ? $short : null;
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
     * Finds the short description and checks the lines before it. Null when
     * the docblock does not start with one, after `@file` or not.
     *
     * @param list<DocblockRow> $rows
     */
    private static function shortDescription(LintContext $context, array $rows, int $first): ?int
    {
        $row = $rows[$first];
        $isFile = $row->tag() === '@file';
        if ($isFile && $row->value() !== '') {
            self::checkFileLine($context, $row);

            return $first;
        }

        $short = self::summaryRow($rows);
        if ($short === null) {
            return null;
        }

        self::checkShort($context, $rows, $isFile ? $first : 0, $short);

        return $short;
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
     * One blank line between the descriptions and the first tag, unless the
     * tag is an example or a link that runs on from the text, or a `phpcs:`
     * line comes right before it.
     *
     * @param list<DocblockRow> $rows
     */
    private static function checkBeforeTags(LintContext $context, array $rows, int $firstTag): void
    {
        $before = DocblockRows::previousContent($rows, $firstTag);
        if (
            $before === null
            || $firstTag === ($before + 2)
            || in_array($rows[$firstTag]->tag(), self::INLINE_TAGS, strict: true)
            || $rows[$before]->isDirective()
        ) {
            return;
        }

        self::report(
            $context,
            Issue::new('Put exactly one blank line before the tags.', $rows[$firstTag]->tagSpan()),
            DocblockRows::oneBlankBetween($context->file, $rows, $before, $firstTag),
        );
    }

    /**
     * Splits the tags at the column of the first one into groups at the
     * blank lines, and reports a `@param`, `@return` or `@throws` section
     * that shares a group with a different tag.
     *
     * @param list<DocblockRow> $rows
     * @param non-empty-list<int> $tags
     * @return non-empty-list<non-empty-list<int>>
     */
    private static function groups(LintContext $context, array $rows, array $tags): array
    {
        $column = $rows[$tags[0]]->column();
        $groups = [[$tags[0]]];
        $previousTag = (string) $rows[$tags[0]]->tag();
        foreach ($tags as $position => $index) {
            if ($position === 0 || $rows[$index]->column() !== $column) {
                continue;
            }

            $previous = self::lastWithText($rows, $tags[$position - 1], $index);
            $current = (string) $rows[$index]->tag();
            $joined = $previous === ($index - 1);
            if (!$joined) {
                $groups[] = [];
            }

            if ($joined && $current !== '@param') {
                self::checkSections($context, $rows, [$previous, $index], [$previousTag, $current]);
            }

            $previousTag = $current;
            $groups[array_key_last($groups)][] = $index;
        }

        return $groups;
    }

    /**
     * Reports a `@param`, `@return` or `@throws` section that touches a
     * different tag with no blank line between them.
     *
     * @param list<DocblockRow> $rows
     * @param array{int, int} $lines The line with text before the tag, and the tag.
     * @param array{string, string} $tags The tag before and the tag.
     */
    private static function checkSections(LintContext $context, array $rows, array $lines, array $tags): void
    {
        [$previous, $index] = $lines;
        [$previousTag, $current] = $tags;
        if (
            $current === $previousTag
            || !in_array($current, self::SECTION_TAGS, strict: true)
            && !in_array($previousTag, self::SECTION_TAGS, strict: true)
            || $rows[$previous]->isDirective()
        ) {
            return;
        }

        self::report(
            $context,
            Issue::new(
                "Put a blank line between the {$previousTag} and {$current} sections.",
                $rows[$index]->tagSpan(),
            ),
            DocblockRows::oneBlankBetween($context->file, $rows, $index - 1, $index),
        );
    }

    /**
     * Reports the `@param` tags that are not in one group. A `@param` is
     * reported when it is not in the group of the first one, or when it is
     * the first one and shares its group with another tag. A group of them
     * that does not come first is reported too, on the first tag of the
     * group, unless the docblock starts with an example, a link or a note.
     *
     * @param list<DocblockRow> $rows
     * @param non-empty-list<non-empty-list<int>> $groups
     */
    private static function checkParams(LintContext $context, array $rows, array $groups): void
    {
        $paramGroup = null;
        foreach ($groups as $group => $indexes) {
            foreach ($indexes as $position => $index) {
                if ($rows[$index]->tag() !== '@param') {
                    continue;
                }

                if ($paramGroup === null && $position > 0 || $paramGroup !== null && $paramGroup !== $group) {
                    $context->report(Issue::new(
                        'Keep the @param tags together, in one group with no other tag and no blank line between them.',
                        $rows[$index]->tagSpan(),
                    ));
                }

                $paramGroup ??= $group;
            }
        }

        $first = strtolower((string) $rows[$groups[0][0]]->tag());
        if ($paramGroup === null || $paramGroup === 0 || in_array($first, self::PARAM_LEADING_TAGS, strict: true)) {
            return;
        }

        $context->report(Issue::new(
            '@param tags must be the first group of tags in a docblock.',
            $rows[$groups[$paramGroup][0]]->tagSpan(),
        ));
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

    /**
     * One blank line after a group that ends with a `@param`, `@return` or
     * `@throws` section, before the next tag at the same column.
     *
     * @param list<DocblockRow> $rows
     * @param non-empty-list<int> $group
     */
    private static function checkAfterGroup(LintContext $context, array $rows, array $group, int $column): void
    {
        $last = $group[count($group) - 1];
        if (!in_array($rows[$last]->tag(), self::SECTION_TAGS, strict: true)) {
            return;
        }

        $next = $last + 1;
        while ($next < count($rows) && $rows[$next]->tag() === null && !$rows[$next]->isDirective()) {
            $next++;
        }

        if ($next === count($rows) || $rows[$next]->column() !== $column) {
            return;
        }

        $previous = $next - 1;
        while ($previous > $last && ($rows[$previous]->isBlank() || $rows[$previous]->isDirective())) {
            $previous--;
        }

        if ($next === ($previous + 2)) {
            return;
        }

        self::report(
            $context,
            Issue::new('Put exactly one blank line after this group of tags.', $rows[$last]->tagSpan()),
            DocblockRows::oneBlankBetween($context->file, $rows, $previous, $next),
        );
    }

    private static function report(LintContext $context, Issue $issue, ?TextEdit $edit): void
    {
        $context->report($edit === null ? $issue : $issue->withEdit($edit));
    }

    /**
     * The last line from $from up to before $to with text after any tag on
     * it, or a directive, or $from when there is none.
     *
     * @param list<DocblockRow> $rows
     */
    private static function lastWithText(array $rows, int $from, int $to): int
    {
        for ($index = $to - 1; $index >= $from; $index--) {
            if ($rows[$index]->value() !== '' || $rows[$index]->isDirective()) {
                return $index;
            }
        }

        return $from;
    }
}
