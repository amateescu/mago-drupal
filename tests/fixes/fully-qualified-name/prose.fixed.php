<?php

namespace Drupal\example;

use Drupal\node\Entity\Node;

/**
 * Loads the node and returns its title.
 */
function node_title(int $id): string
{
    return Node::load($id)->label();
}
