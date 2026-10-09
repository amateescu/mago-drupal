<?php

class Holder
{
    public const list = 1;
    public $list = [];

    public function list(int $count): int
    {
        return $count;
    }
}

function near_miss(Holder $holder, array $pair): int
{
    // list($a) = $pair;
    [$a, $b] = $pair;
    $list = $holder->list(1) + $holder?->list(2) + Holder::list;
    $text = 'list($x) = $y';

    return $a + $b + $list + strlen($text) + strpos(haystack: $text, needle: 'x');
}
