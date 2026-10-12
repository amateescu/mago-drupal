<?php

function calls($a, $b): array
{
    return [
        // The call runs once instead of twice.
        $a->get() ? $a->get() : $b,
        !$a->get() ? $b : $a->get(),
        // The middle part would need parentheses at the end.
        !$a ? $b ? 1 : 2 : $a,
        !$a ? $b = 1 : $a,
        // Not the same operand.
        $a ? $b : $a,
        !$a ? $a : $b,
        $a ?: $b,
    ];
}
