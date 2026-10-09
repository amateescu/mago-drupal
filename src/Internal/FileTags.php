<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Reporting\Safety;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;

use function array_slice;
use function implode;
use function preg_match;
use function preg_split;
use function strlen;
use function substr;
use function substr_count;

/**
 * Edits and positions for the `@file` tag of a file docblock.
 *
 * @internal
 */
final class FileTags
{
    private function __construct() {}

    /**
     * Rewrites the docblock with its `@file` line moved right below the
     * opener. The fix applies only when the opener is alone on its line and
     * the tag has no text after it. A blank star line that the move leaves
     * next to the closer or another blank star line goes with it. Moving
     * text makes the edit potentially unsafe.
     */
    public static function move(string $contents, Span $docblock, Span $tag): ?TextEdit
    {
        $lines = preg_split('/(?<=\n)/', substr($contents, $docblock->start, $docblock->length()));
        if ($lines === false || preg_match('/^\/\*\*[ \t]*\r?\n$/', $lines[0]) !== 1) {
            return null;
        }

        $index = self::lineOf($lines, $tag->start - $docblock->start);
        $found = [];
        if ($index === null || preg_match('/^([ \t]*\*[ \t]*)@file[ \t]*(\r?\n)$/', $lines[$index], $found) !== 1) {
            return null;
        }

        $after = $lines[$index + 1] ?? '';
        $dropBefore =
            $index > 1
            && preg_match('/^[ \t]*\*?[ \t]*\r?\n$/', $lines[$index - 1]) === 1
            && (preg_match('/^[ \t]*\*\/$/', $after) === 1 || preg_match('/^[ \t]*\*?[ \t]*\r?\n$/', $after) === 1);
        $kept = array_slice($lines, offset: 1, length: $index - 1 - ($dropBefore ? 1 : 0));
        $text =
            $lines[0]
            . $found[1]
            . '@file'
            . $found[2]
            . implode('', $kept)
            . implode('', array_slice($lines, offset: $index + 1));

        return TextEdit::replace($docblock, $text)->withSafety(Safety::PotentiallyUnsafe);
    }

    /**
     * Whether the tag is on the line right below the docblock's opener.
     */
    public static function onSecondLine(string $contents, Span $docblock, Span $tag): bool
    {
        return substr_count(substr($contents, $docblock->start, $tag->start - $docblock->start), needle: "\n") === 1;
    }

    /**
     * Where `@file` goes, at the end of the opener line, or null when it
     * cannot go on a line of its own: the opener line holds text, the
     * docblock is a group's, or no blank line parts it from the code below.
     * A docblock right on a function is that function's, which `@file`
     * would steal.
     */
    public static function insertOffset(string $contents, Span $docblock): ?int
    {
        $text = substr($contents, $docblock->start, $docblock->length());
        $opener = [];
        if (
            preg_match('/^\/\*\*[ \t]*(?=\r?\n)/', $text, $opener) !== 1
            || preg_match('/@(?:defgroup|addtogroup)\b/', $text) === 1
            || preg_match('/\G\r?\n[ \t]*\r?\n/', $contents, offset: $docblock->end) !== 1
        ) {
            return null;
        }

        return $docblock->start + strlen($opener[0]);
    }

    /**
     * Where Coder reports a file docblock problem: the first star line below
     * the opener, or the opener when there is none.
     */
    public static function reportSpan(string $contents, Span $docblock): Span
    {
        $before = [];
        $text = substr($contents, $docblock->start, $docblock->length());
        if (preg_match('/^(.*?\n[ \t]*)\*(?!\/)/s', $text, $before) !== 1) {
            return $docblock;
        }

        $start = $docblock->start + strlen($before[1]);

        return new Span($start, $start + 1);
    }

    /**
     * The index of the line that holds a byte offset of the docblock text.
     *
     * @param list<string> $lines
     */
    private static function lineOf(array $lines, int $offset): ?int
    {
        $start = 0;
        foreach ($lines as $number => $line) {
            $start += strlen($line);
            if ($start > $offset) {
                return $number;
            }
        }

        return null;
    }
}
