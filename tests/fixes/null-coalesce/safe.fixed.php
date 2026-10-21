<?php

function isset_shapes($a, $b, $obj): array
{
    return [
        $a ?? 'default',
        $a['key'] ?? '',
        $a['k'] ?? '',
        $a['k'] ?? '',
        isset($obj->a->b) ? $obj->a->b : NULL,
        self::$cache ?? NULL,
        self::$cache['k'] ?? [],
        $obj->a ?? NULL,
        $obj?->a ?? NULL,
        $a ?? '',
        ($a ?? ''),
        $b and $a ?? '',
        $a ?? '',
        $a ?? ($b ? 1 : 2),
        $a['#key'] ?? '',
        $a['//'] ?? '',
    ];
}

function null_shapes($a, $b, $obj): array
{
    return [
        $a ?? '',
        $a ?? '',
        $a ?? '',
        $a ?? '',
        $a ?? '',
        $a ?? '',
        $a['k'] === null ? '' : $a [ 'k' ],
        $obj?->p === null ? 'x' : $obj?->p,
        FOO_CONST ?? 'x',
        $a ?? 'x',
        $a ?? ($b ? 1 : 2),
        $b and $a ?? '',
        $a ?? '#none',
    ];
}
