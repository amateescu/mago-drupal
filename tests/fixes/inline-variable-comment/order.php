<?php

function inline_variable_order(array $rows): void
{
    /** @var $weight int */
    $weight = $rows[0];
    /** @var $map array<string, int> The weights. */
    $map = $rows[1];
    /** @var $broken array<string, */
    $broken = $rows[2];
    /** @var $spaced int | string */
    /** @var $literal "a b" */
    $literal = $rows[7];
    $spaced = $rows[3];
    /**
     * @var $multi
     *   int
     */
    $multi = $rows[4];
    /**
     * @var $names array<int, string>
     *   The names by row.
     */
    $names = $rows[8];
    // @var \Drupal\node\NodeInterface $node
    $node = $rows[5];
}
