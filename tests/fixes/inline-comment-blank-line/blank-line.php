<?php

/**
 * Holds comments with a blank line below them.
 */
function blank_line_below(array $items): array
{
    // Counts the items.

    $count = count($items);
    // The first paragraph.

    // The second paragraph.
    $count++;
    // Two lines, the second
    // with a blank line below.

    $count++;
    $items[] = $count; // A trailing comment
    // continued below.

    // @see blank_line_below()

    //

    // Sits on a docblock.

    /** @var int $total */
    $total = $count;
    // @codingStandardsIgnoreStart
    $total++;
    // @codingStandardsIgnoreEnd

    // phpcs:ignore Some.Sniff

    $items = [
        $total,
        // Before a closing bracket, which the formatter takes.

    ];
    // Before a closing brace, which the formatter takes.

}

class BlankLineBelow
{
    public int $count = 0;
    // Before the class's closing brace, which the formatter takes.

}
