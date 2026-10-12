<?php

function repeated($a, $b, $obj): array
{
    return [
        $a ? $a : 'default',
        $a['key'] ? $a['key'] : '',
        $a["k"] ? $a['k'] : '',
        $obj->a->b ? $obj->a->b : NULL,
        self::$cache ? self::$cache : [],
        ($a) ? $a : '',
        $a
            ? $a
            : $b,
    ];
}

function negated($a, $b): array
{
    return [
        !$a ? $b : $a,
        !$a['k'] ? 'none' : $a['k'],
        (!$a) ? $b : $a,
    ];
}
