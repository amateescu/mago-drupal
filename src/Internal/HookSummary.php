<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Span;

use function count;
use function preg_match;
use function str_contains;
use function strlen;

/**
 * Checks the "Implements hook_x()." summary of a hook implementation the way
 * Coder's hook comment sniff does. `drupal/hook-comment` and
 * `drupal/hook-method-comment` share it.
 *
 * @internal
 */
final class HookSummary
{
    private const IMPLEMENTS_HOOK = '/^Implement[^\n]+?hook_[^\n]+/i';

    private const WELL_FORMED = '/ (drush_)?hook_[a-zA-Z0-9_]+\(\)( for .+)?\.$/';

    private function __construct() {}

    /**
     * Checks a summary that starts with "Implements" and names a hook, and
     * reports the `@param` and `@return` tags of the docblock. Returns false,
     * without reporting anything, for any other summary.
     *
     * @param list<DocblockLine> $summary
     */
    public static function check(LintContext $context, Span $docblock, array $summary): bool
    {
        $short = self::text($summary);
        if (preg_match(self::IMPLEMENTS_HOOK, $short) !== 1) {
            return false;
        }

        $wellFormed =
            str_contains($short, 'Implements ')
            && !str_contains($short, 'Implements of')
            && preg_match(self::WELL_FORMED, $short) === 1;

        if (!$wellFormed) {
            $context->report(Issue::new(
                'Use the format "Implements hook_foo().", "Implements hook_foo_BAR_ID_bar() for xyz_bar().", or a similar hook_ reference.',
                self::span($summary),
            ));

            return true;
        }

        foreach (Docblocks::tags($context->file, $docblock) as $tag) {
            if ($tag->name === 'param') {
                $context->report(Issue::new(
                    'Do not repeat the @param documentation in a hook implementation.',
                    $tag->nameSpan,
                ));
            }

            if ($tag->name === 'return') {
                $context->report(Issue::new(
                    'Do not repeat the @return documentation in a hook implementation.',
                    $tag->nameSpan,
                ));
            }
        }

        return true;
    }

    /**
     * Joins a paragraph's lines into one string with nothing between them,
     * as Coder's sniff does.
     *
     * @param list<DocblockLine> $paragraph
     */
    public static function text(array $paragraph): string
    {
        $text = '';
        foreach ($paragraph as $line) {
            $text .= $line->text;
        }

        return $text;
    }

    /**
     * @param list<DocblockLine> $paragraph
     */
    public static function span(array $paragraph): Span
    {
        $last = $paragraph[count($paragraph) - 1];

        return new Span($paragraph[0]->offset, $last->offset + strlen($last->text));
    }
}
