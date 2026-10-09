<?php

/**
 * Holds the comment lines.
 */
function inline_capital(): void
{
    // lowercase start.
    $a = 1;
    //
    // after an empty line.
    $b = 2;
    // machine_name stays, a code reference.
    $c = 3;
    // Already capital.
    $d = 4;
    //tight start, which gets a space first.
    $e = 5;
    if ($e > 1) {
        $e = 1;
    } // end of the if, which stays as it is.
    // below the brace, a comment of its own.
    $f = 6;
}
