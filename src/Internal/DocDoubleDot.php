<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Safety;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;

use function count;
use function preg_match;
use function rtrim;
use function strlen;
use function substr;

/**
 * A description line that ends in two dots, for `drupal/doc-comment`.
 *
 * Ports Drupal's pattern for SlevomatCodingStandard.Commenting.ForbiddenComments.
 * Coder reads every line of the description, up to the first tag, in every
 * docblock. Three dots or more are fine.
 *
 * @internal
 */
final class DocDoubleDot
{
    private const PATTERN = '/(?<!\.)\.\.(?!\.)$/';

    private function __construct() {}

    /**
     * Reports each description line that ends in two dots, with a fix that
     * removes one of them.
     *
     * A docblock that starts with a tag has no description. A `phpcs:` line
     * is not text, and the lines after it still count.
     */
    public static function check(LintContext $context, Span $span): void
    {
        $rows = DocblockRows::of($context->file, $span);
        $first = DocblockRows::nextContent($rows, -1);
        if ($first === null || !$rows[$first]->isProse()) {
            return;
        }

        for ($index = $first; $index < count($rows) && $rows[$index]->tag() === null; $index++) {
            $row = $rows[$index];
            if ($row->isDirective() || preg_match(self::PATTERN, $row->text) !== 1) {
                continue;
            }

            // The dots and the spaces before them become one full stop, so
            // the line keeps its end. Coder's fix removes both dots.
            $kept = strlen(rtrim(substr($row->text, offset: 0, length: -2)));
            $context->report(Issue::new(
                'End the line with one full stop, not two.',
                new Span($row->textEnd - 2, $row->textEnd),
            )->withEdit(TextEdit::replace(
                new Span($row->textEnd - strlen($row->text) + $kept, $row->textEnd),
                '.',
            )->withSafety(Safety::PotentiallyUnsafe)));
        }
    }
}
