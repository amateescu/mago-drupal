<?php

function repeated($a, $b, $obj): array
{
    return [
        $a ?: 'default',
        $a['key'] ? $a['key'] : '',
        $a["k"] ? $a['k'] : '',
        $obj->a->b ? $obj->a->b : NULL,
        self::$cache ?: [],
        $a ?: $b,
    ];
}

function negated($a, $b): array
{
    return [
        $a ?: $b,
        !$a['k'] ? 'none' : $a['k'],
    ];
}
