<?php

function else_if(bool $a, bool $b): int
{
    if ($a) {
        return 1;
    }
    else if ($b) {
        return 2;
    }
    else   if (!$b) {
        return 3;
    }

    return 0;
}
