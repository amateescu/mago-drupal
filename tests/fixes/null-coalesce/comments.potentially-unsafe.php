<?php

function comments($a): array
{
    return [
        isset($a) /* pick */ ? $a : '',
        isset($a /* arg */) ? $a : '',
        $a === null /* none */ ? '' : $a,
        $a === null ? '' : /* keep */ $a,
        $a /* x */ === /* y */ null ? '' : $a,
        $a === null
            // Empty value.
            ? ''
            // Value kept.
            : $a,
    ];
}
