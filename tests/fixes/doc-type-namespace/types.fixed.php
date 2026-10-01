<?php

namespace Drupal\example;

use Drupal\Core\Url;
use Drupal\node\NodeInterface as Node;
use Drupal\Core\Entity\EntityInterface;

/**
 * Takes imported types.
 *
 * @param \Drupal\Core\Url $url
 *   The url.
 * @param ?\Drupal\node\NodeInterface|\Drupal\Core\Entity\EntityInterface[] $entities
 *   The entities.
 * @param array<Url> $urls
 *   The urls, whose generic member stays as it is.
 *
 * @return \Drupal\Core\Url|null
 *   The url, if any.
 */
function types($url, $entities, $urls)
{
    return $url;
}

/**
 * Has the type on the next line.
 *
 * @param
 *   Url $url The url.
 */
function next_line($url): void
{
}
