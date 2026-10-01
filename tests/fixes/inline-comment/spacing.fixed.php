<?php

/**
 * Holds the comment lines.
 */
function inline_spacing(): void
{
    // No space.
    $a = 1;
    // A tab.
    $b = 2;
    // Three spaces.
    $c = 3;
    // Three slashes.
    $d = 4; // Trailing.
    // A list:
    // - An item that
    //   wraps too far.
    // 1. A numbered item that
    //    wraps short.
    // @todo Fix this
    //   later.
    // A line, then
    //   a deeper one, which is reported without a fix.
    if ($a) {
    } //After a brace.
    // An example:
    // @code
    //   $x = 1;
    //$y = 2;
    // @endcode
    //phpcs:ignore Some.Sniff
    //
}
