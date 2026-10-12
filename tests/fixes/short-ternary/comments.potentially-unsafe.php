<?php

function comments($a, $b): array
{
    return [
        $a /* pick */ ? $a : $b,
        $a ? /* same */ $a : $b,
        !$a ? $b : /* kept */ $a,
    ];
}
