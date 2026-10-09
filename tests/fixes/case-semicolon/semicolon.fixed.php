<?php

function semicolons(int $x): int
{
    switch ($x) {
        case 1:
            return 1;

        case 2:
            return 2;

        case 3:
            return 3;

        CASE 4:
            return 4;

        case ($x > 5 ? 6 : 7):
            return 5;

        case $x ? 8 : 9:
            return 6;

        default:
            return 0;
    }
}

function nested(int $x, int $y): int
{
    switch ($x) {
        case 1:
            switch ($y) {
                case 2:
                    return 2;

                default:
                    return 3;
            }

        default:
            return 0;
    }
}

function alternative(int $x): int
{
    switch ($x):
        case 1:
            return 1;

        default:
            return 0;
    endswitch;
}

function comments(int $x): int
{
    switch ($x) {
        case 1 /* one */:
            return 1;

        case 2 // two
        :
            return 2;

        default /* other */:
            return 0;
    }
}
