<?php

namespace Drupal\example;

/**
 * Loads the node and returns its title.
 */
function node_title(int $id): string
{
    return \Drupal\node\Entity\Node::load($id)->label();
}
