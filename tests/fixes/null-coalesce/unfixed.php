<?php

function reported_without_fix($a, $b, $obj, $i): array
{
    return [
        (string) isset($a) ? $a : '',
        isset($a[$i++]) ? $a[$i++] : '',
        isset($a[max(1, 2)]) ? $a[max(1, 2)] : '',
        $obj->get() === null ? '' : $obj->get(),
        foo($a) === null ? 'x' : foo($a),
        $a === null ? $b ? 1 : 2 : $a,
        isset($a) ? $a : $c = 1,
    ];
}

function not_reported($a, $b): array
{
    return [
        !isset($a) ? '' : $a,
        $b && isset($a) ? $a : '',
        isset($a, $b) ? $a : '',
        isset($a) ?: '',
        $a == null ? '' : $a,
        !$a === null ? '' : $a,
        (string) $a === null ? '' : $a,
        $b + $a === null ? '' : $a,
        (array) isset($a) ? $a : '',
    ];
}
