<?php

/**
 * Holds the comment lines.
 */
function inline_capital(): void
{
    // Lowercase start.
    $a = 1;
    //
    // After an empty line.
    $b = 2;
    // machine_name stays, a code reference.
    $c = 3;
    // Already capital.
    $d = 4;
    // Tight start, which gets a space first.
    $e = 5;
    if ($e > 1) {
        $e = 1;
    } // end of the if, which stays as it is.
    // Below the brace, a comment of its own.
    $f = 6;
}
