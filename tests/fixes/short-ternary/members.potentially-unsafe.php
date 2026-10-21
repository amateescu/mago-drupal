<?php

function members($a, $b, $obj): array
{
    return [
        $a['key'] ? $a['key'] : '',
        $obj->a ? $obj->a : NULL,
        $obj?->a ? $obj?->a : NULL,
        self::$cache['k'] ? self::$cache['k'] : [],
        $b[$obj->k] ? $b[$obj->k] : 0,
        !$obj->a ? 'none' : $obj->a,
        self::$cache ? self::$cache : [],
    ];
}
