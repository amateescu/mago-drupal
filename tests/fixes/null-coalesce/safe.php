<?php

function isset_shapes($a, $b, $obj): array
{
    return [
        isset($a) ? $a : 'default',
        isset($a['key']) ? $a['key'] : '',
        isset( $a [ 'k' ] ) ? $a['k'] : '',
        isset($a["k"]) ? $a['k'] : '',
        isset($obj->a->b) ? $obj->a->b : NULL,
        isset(self::$cache) ? self::$cache : NULL,
        isset($a) ? ($a) : '',
        (isset($a) ? $a : ''),
        $b and isset($a) ? $a : '',
        isset($a)
            ? $a
            : '',
        isset($a) ? $a : ($b ? 1 : 2),
    ];
}

function null_shapes($a, $b, $obj): array
{
    return [
        $a === null ? '' : $a,
        $a !== null ? $a : '',
        null === $a ? '' : $a,
        null !== $a ? $a : '',
        $a === NULL ? '' : $a,
        $a === \null ? '' : $a,
        $a['k'] === null ? '' : $a [ 'k' ],
        $obj?->p === null ? 'x' : $obj?->p,
        FOO_CONST === null ? 'x' : FOO_CONST,
        ($a) === null ? 'x' : $a,
        $a === null ? ($b ? 1 : 2) : $a,
        $b and $a === null ? '' : $a,
    ];
}
