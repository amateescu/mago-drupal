<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;

use function in_array;
use function strspn;

/**
 * The space after the star of each docblock line, for `drupal/doc-comment`.
 *
 * Ports Drupal.Commenting.DocCommentAlignment. A line needs one space after
 * its star. Coder also reports extra spaces or a tab, but only before the
 * tags in `SPACED_TAGS`. Other text may be indented after its star.
 *
 * @internal
 */
final class DocStarSpacing
{
    /**
     * The tags that Coder checks the space before.
     */
    private const SPACED_TAGS = ['@param', '@return', '@throws', '@ingroup', '@var'];

    private function __construct() {}

    /**
     * Reports the lines of a docblock with no space after the star, and the
     * lines with the wrong space before a tag.
     *
     * The line at `$summaryRow` is left out. It is the short description,
     * and `DocCommentSpacing` checks the space after its star.
     */
    public static function check(LintContext $context, Span $span, ?int $summaryRow): void
    {
        if (!AlignedDocblocks::isChecked($context->file, $span)) {
            return;
        }

        foreach (DocblockRows::of($context->file, $span) as $index => $row) {
            if ($row->star === null || $index === $summaryRow) {
                continue;
            }

            self::checkRow($context, $row, $row->star);
        }
    }

    /**
     * Checks the space after the star of one line.
     */
    private static function checkRow(LintContext $context, DocblockRow $row, int $star): void
    {
        $contents = $context->file->contents;
        $after = $star + 1;
        $spaces = strspn($contents, characters: " \t", offset: $after);
        $next = $contents[$after + $spaces] ?? "\n";
        if ($next === "\n" || $next === "\r") {
            return;
        }

        if ($spaces === 0) {
            $context->report(Issue::new(
                'Put a space between the star and the text.',
                new Span($star, $after),
            )->withEdit(TextEdit::insert($after, ' ')));

            return;
        }

        $gap = new Span($after, $after + $spaces);
        $isOneSpace = $spaces === 1 && $contents[$after] === ' ';
        if ($isOneSpace || !in_array($row->tag(), self::SPACED_TAGS, strict: true)) {
            return;
        }

        $context->report(Issue::new(
            "Put exactly one space between the star and {$row->tag()}.",
            $gap,
        )->withEdit(TextEdit::replace($gap, ' ')));
    }
}
