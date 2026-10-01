<?php

function inline_variable_delimiters(array $rows): void
{
    // @var \Drupal\node\NodeInterface $node
    $node = $rows[0];
    # @var string $label
    $label = $rows[1];
    /* @var int $count */
    $count = $rows[2];
    // @var $items array<int, string>
    $items = $rows[3];
    // The row holds a node. @var \Drupal\node\NodeInterface $other
    $other = $rows[4];
    $weight = $rows[5]; // @var int $weight

    // Explains the next line.
    // @var int $first
    $first = $rows[6];
    // @var nothing here
    $none = $rows[7];
    // /** @var int $old */
    $old = $rows[8];
}
