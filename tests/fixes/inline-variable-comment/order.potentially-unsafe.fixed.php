<?php

function inline_variable_order(array $rows): void
{
    /** @var int $weight */
    $weight = $rows[0];
    /** @var array<string, int> $map The weights. */
    $map = $rows[1];
    /** @var $broken array<string, */
    $broken = $rows[2];
    /** @var $spaced int | string */
    /** @var "a b" $literal */
    $literal = $rows[7];
    $spaced = $rows[3];
    /**
     * @var $multi
     *   int
     */
    $multi = $rows[4];
    /** @var \Drupal\node\NodeInterface $node */
    $node = $rows[5];
}
