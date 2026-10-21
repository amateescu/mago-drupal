<?php

function members($a, $b, $obj): array
{
    return [
        $a['key'] ?: '',
        $obj->a ?: NULL,
        $obj?->a ?: NULL,
        self::$cache['k'] ?: [],
        $b[$obj->k] ?: 0,
        $obj->a ?: 'none',
        self::$cache ?: [],
    ];
}
