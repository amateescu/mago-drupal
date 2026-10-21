<?php

namespace Drupal\example;

USE Drupal\node\NodeInterface AS Node;

/**
 * Takes an imported type.
 *
 * @param Node $node
 *   The node.
 */
function keyword_case($node)
{
    return $node;
}
