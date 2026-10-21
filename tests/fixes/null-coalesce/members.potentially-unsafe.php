<?php

function members($a, $b, $obj): array
{
    return [
        $a['key'] === null ? '' : $a['key'],
        $obj->a !== null ? $obj->a : NULL,
        null === $obj?->a ? 'x' : $obj?->a,
        self::$cache['k'] !== null ? self::$cache['k'] : [],
        $b[$obj->k] === null ? 0 : $b[$obj->k],
        isset($obj->a) ? $obj->a : NULL,
        isset($obj->data['value']) ? $obj->data['value'] : 0,
        isset($a['k']['j']) ? $a['k']['j'] : 0,
        isset($b[$obj->k]) ? $b[$obj->k] : 0,
        isset($obj->a->b) ? $obj->a->b : NULL,
        isset($obj->class::$x) ? $obj->class::$x : NULL,
        self::$cache === null ? [] : self::$cache,
    ];
}
