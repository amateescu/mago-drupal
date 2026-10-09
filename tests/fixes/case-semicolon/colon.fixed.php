<?php

function colons(int $x): int
{
    switch ($x) {
        case 1:
            return 1;

        case 2 :
            return 2;

        default:
            return match ($x) {
                3 => 3,
                default => 0,
            };
    }
}
