<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;

use function array_key_exists;
use function count;
use function in_array;
use function preg_match;
use function str_ends_with;
use function strlen;
use function trim;

/**
 * The whitespace checks of Drupal.Commenting.FunctionComment, for
 * `drupal/function-comment`: the indent of the `@param`, `@return` and
 * `@throws` descriptions, the space after a `@param` type, and a `@param`
 * description written on the tag's line.
 *
 * A description line must be indented three spaces from the star, the first
 * one exactly three. The lines each check covers follow Coder: a `@param`
 * description runs past `@code`, `@endcode` and `@link` at the tag's column
 * up to the next tag there, and a `@return` or `@throws` description stops
 * at the next tag of any kind.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class FunctionCommentSpacing
{
    /**
     * Coder's split of a `@param` value: the type, the variable, and the
     * space and text after it.
     */
    private const PARAM_PATTERN = '/((?:(?![$.]|&(?=\$)).)*)(?:((?:\.\.\.)?(?:\$|&)[^\s]+)(?:(\s+)(.*))?)?/';

    /**
     * Tags a `@param` description may hold at the tag's own column.
     */
    private const PARAM_INLINE_TAGS = ['@code', '@endcode', '@link'];

    private function __construct() {}

    public static function check(LintContext $context, Span $span): void
    {
        $rows = DocblockRows::of($context->file, $span);
        $tags = DocblockRows::tags($rows);
        $returns = [];
        foreach ($tags as $position => $index) {
            $tag = $rows[$index]->tag();
            $end = $tags[$position + 1] ?? count($rows);
            if ($tag === '@param') {
                self::checkParam($context, $rows, $tags, $position);

                continue;
            }

            if ($tag === '@return') {
                $returns[] = [$index, $end];

                continue;
            }

            if ($tag === '@throws' && $rows[$index]->value() !== '') {
                self::checkIndent($context, $rows, $index, $end);
            }
        }

        // Coder skips the `@return` description when there are two tags.
        if (count($returns) === 1) {
            self::checkIndent($context, $rows, $returns[0][0], $returns[0][1]);
        }
    }

    /**
     * @param list<DocblockRow> $rows
     * @param list<int> $tags
     */
    private static function checkParam(LintContext $context, array $rows, array $tags, int $position): void
    {
        $index = $tags[$position];
        $row = $rows[$index];
        $value = $row->value();
        if ($value === '') {
            return;
        }

        $matches = [];
        preg_match(self::PARAM_PATTERN, $value, $matches);
        $valueStart = $row->textEnd - strlen($value);
        $typeLength = strlen(trim($matches[1]));
        $variable = $matches[2] ?? '';
        $after = $matches[4] ?? '';
        if ($after !== '' && ($typeLength > 0 || preg_match('/\S+\s+\S+/', $after) === 1)) {
            $spaceStart = $valueStart + strlen($matches[1]) + strlen($variable);
            self::reportNewLine($context, $row, new Span($spaceStart, $spaceStart + strlen($matches[3])));
        }

        // Coder skips the rest of a tag whose name ends in a period.
        if (str_ends_with($variable, '.')) {
            return;
        }

        if ($variable !== '' && $typeLength > 0 && (strlen($matches[1]) - $typeLength) !== 1) {
            $space = new Span($valueStart + $typeLength, $valueStart + strlen($matches[1]));
            $context->report(Issue::new(
                'Put exactly one space between the @param type and the variable name.',
                new Span($valueStart, $valueStart + $typeLength),
            )->withEdit(TextEdit::replace($space, ' ')));
        }

        self::checkIndent($context, $rows, $index, self::paramEnd($rows, $tags, $position));
    }

    /**
     * Where a `@param` description ends: at the next tag at its column past
     * the inline ones, or at the end of the docblock when the last tag is
     * indented into it.
     *
     * @param list<DocblockRow> $rows
     * @param list<int> $tags
     */
    private static function paramEnd(array $rows, array $tags, int $position): int
    {
        if (!array_key_exists($position + 1, $tags)) {
            return count($rows);
        }

        $column = $rows[$tags[$position]]->column();
        $skip = $position + 1;
        while (
            array_key_exists($skip + 1, $tags)
            && (
                in_array($rows[$tags[$skip]]->tag(), self::PARAM_INLINE_TAGS, strict: true)
                || $rows[$tags[$skip]]->column() !== $column
            )
        ) {
            $skip++;
        }

        return $rows[$tags[$skip]]->column() === ($column + 2) ? count($rows) : $tags[$skip];
    }

    /**
     * Reports a description line indented less than three spaces from the
     * star, and a first line indented more. Coder lets the first line of a
     * `@throws` description go deeper.
     *
     * @param list<DocblockRow> $rows
     */
    private static function checkIndent(LintContext $context, array $rows, int $tag, int $end): void
    {
        $name = (string) $rows[$tag]->tag();
        $first = true;
        for ($index = $tag + 1; $index < $end; $index++) {
            $row = $rows[$index];
            if ($row->star === null || $row->value() === '') {
                continue;
            }

            $space = new Span($row->star + 1, $row->textStart);
            $tooDeep = $first && $name !== '@throws' && $space->length() > 3;
            $first = false;
            if ($space->length() >= 3 && !$tooDeep) {
                continue;
            }

            $context->report(Issue::new(
                "Indent the {$name} description three spaces from the star.",
                $row->textSpan(),
            )->withEdit(TextEdit::replace($space, '   ')));
        }
    }

    /**
     * Reports a `@param` description on the tag's line, which goes on the
     * line below.
     */
    private static function reportNewLine(LintContext $context, DocblockRow $row, Span $space): void
    {
        $issue = Issue::new(
            'Put the @param description on the line below the tag.',
            new Span($space->end, $row->textEnd),
        );
        $prefix = DocblockRows::prefix($context->file, $row);
        $context->report(
            $prefix === null
                ? $issue
                : $issue->withEdit(TextEdit::replace(
                    $space,
                    LineEnding::of($context->file->contents) . $prefix . '   ',
                )),
        );
    }
}
