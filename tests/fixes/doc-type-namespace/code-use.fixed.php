<?php

namespace Drupal\example;

use Drupal\Core\Url;

/**
 * Takes a url that the code names too, and a node that only the docblock names.
 *
 * @param Url $url
 *   The url, which keeps its short name.
 * @param \Drupal\node\NodeInterface $node
 *   The node, whose import goes.
 */
function code_use(Url $url, $node): void
{
}
