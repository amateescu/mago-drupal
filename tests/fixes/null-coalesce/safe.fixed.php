<?php

function isset_shapes($a, $b, $obj): array
{
    return [
        $a ?? 'default',
        $a['key'] ?? '',
        $a['k'] ?? '',
        $a['k'] ?? '',
        $obj->a->b ?? NULL,
        self::$cache ?? NULL,
        $a ?? '',
        ($a ?? ''),
        $b and $a ?? '',
        $a ?? '',
        $a ?? ($b ? 1 : 2),
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
        $a['k'] ?? '',
        $obj?->p ?? 'x',
        FOO_CONST ?? 'x',
        $a ?? 'x',
        $a ?? ($b ? 1 : 2),
        $b and $a ?? '',
    ];
}
