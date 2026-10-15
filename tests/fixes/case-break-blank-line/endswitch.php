<?php

/**
 * Ends the cases of an endswitch body with no blank line, or with too many.
 */
function case_break_alternative(int $a): int
{
    switch ($a):
        case 1:
            $b = 1;
            break;
        case 2:
            $b = 2;
            break;


        case 3:
            return 3;
        default:
            $b = 0;
            break;
    endswitch;

    return $b;
}
