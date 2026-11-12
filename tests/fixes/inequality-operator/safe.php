<?php

function inequality(int $a, int $b): bool
{
    if ($a <> 0) {
        return $a<>$b;
    }

    return $a != $b || '<>' === (string) $b;
}
