<?php

function repeated($a, $b, $obj): array
{
    return [
        $a ?: 'default',
        $a['key'] ?: '',
        $a["k"] ?: '',
        $obj->a->b ?: NULL,
        self::$cache ?: [],
        ($a) ?: '',
        $a ?: $b,
    ];
}

function negated($a, $b): array
{
    return [
        $a ?: $b,
        $a['k'] ?: 'none',
        $a ?: $b,
    ];
}
