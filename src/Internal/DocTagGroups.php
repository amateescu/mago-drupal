<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;

use function array_key_last;
use function count;
use function in_array;
use function strtolower;

/**
 * The tag group checks of Drupal.Commenting.DocComment, for
 * `drupal/doc-tag-groups`: the blank lines before the tags, around the
 * `@param`, `@return` and `@throws` sections and after a group, and the
 * `@param` tags in one group that comes first.
 *
 * Only the tags at the column of the first one form groups, so a tag
 * indented into a description is not one of them. Coder skips these checks
 * on a docblock that does not start with a description, or with `@file`
 * and one.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class DocTagGroups
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

    public static function check(LintContext $context, Span $span): void
    {
        $rows = DocblockRows::of($context->file, $span);
        $start = DocblockSummary::start($rows);
        if ($start === null) {
            return;
        }

        [$first, $short] = $start;
        $tags = DocblockRows::tags($rows);
        if (DocblockRows::nextContent($rows, DocblockSummary::shortEnd($rows, $short)) === null || $tags === []) {
            return;
        }

        if ($rows[$first]->tag() !== '@file') {
            self::checkBeforeTags($context, $rows, $tags[0]);
        }

        [$groups, $joins] = self::walk($rows, $tags);
        foreach ($joins as [$lines, $names]) {
            self::checkSections($context, $rows, $lines, $names);
        }

        self::checkParams($context, $rows, $groups);
        foreach ($groups as $group) {
            self::checkAfterGroup($context, $rows, $group, $rows[$tags[0]]->column());
        }
    }

    /**
     * Splits the tags at the column of the first one into groups at the
     * blank lines.
     *
     * @param list<DocblockRow> $rows
     * @param non-empty-list<int> $tags
     * @return non-empty-list<non-empty-list<int>>
     */
    public static function groups(array $rows, array $tags): array
    {
        return self::walk($rows, $tags)[0];
    }

    /**
     * The groups, and each tag other than a `@param` that follows the tag
     * before it with no blank line: the line with text before it and its own
     * line, and the two tag names.
     *
     * @param list<DocblockRow> $rows
     * @param non-empty-list<int> $tags
     * @return array{non-empty-list<non-empty-list<int>>, list<array{array{int, int}, array{string, string}}>}
     */
    private static function walk(array $rows, array $tags): array
    {
        $column = $rows[$tags[0]]->column();
        $groups = [[$tags[0]]];
        $joins = [];
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
                $joins[] = [
                    [$previous,    $index],
                    [$previousTag, $current],
                ];
            }

            $previousTag = $current;
            $groups[array_key_last($groups)][] = $index;
        }

        return [$groups, $joins];
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
