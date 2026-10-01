<?php

function else_if(bool $a, bool $b): int
{
    if ($a) {
        return 1;
    }
    elseif ($b) {
        return 2;
    }
    elseif (!$b) {
        return 3;
    }

    return 0;
}
