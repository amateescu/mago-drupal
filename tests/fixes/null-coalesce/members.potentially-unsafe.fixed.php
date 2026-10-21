<?php

function members($a, $b, $obj): array
{
    return [
        $a['key'] ?? '',
        $obj->a ?? NULL,
        $obj?->a ?? 'x',
        self::$cache['k'] ?? [],
        $b[$obj->k] ?? 0,
        $obj->a ?? NULL,
        $obj->data['value'] ?? 0,
        $a['k']['j'] ?? 0,
        $b[$obj->k] ?? 0,
        $obj->a->b ?? NULL,
        $obj->class::$x ?? NULL,
        self::$cache ?? [],
    ];
}
