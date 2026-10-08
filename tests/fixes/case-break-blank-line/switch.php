<?php

/**
 * Ends its cases with no blank line, or with too many.
 */
function case_break(int $a): int
{
    switch ($a) {
        case 1:
            $b = 1;
            break;
        case 2:
            $b = 2;
            break;


        case 3:
            return 3;
            // A comment on the line below counts as the next line.

        case 4:
            $b = 4;
            break; // A comment on the same line does not.
        case 5:
            return 5; case 6:
            $b = 6;
            break;

        case 7:
            throw new \RuntimeException('seven');
        default:
            $b = 0;
            break;
        case 8:
            return 8;
    }

    switch ($a) {
        default:
        case 9:
            $b = 9;
            break;
        case 10:
            return 10;
    }

    return $b;
}
