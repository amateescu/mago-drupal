<?php

namespace Drupal\example;

use Drupal\Core\Url;

/**
 * Takes imported types.
 *
 * @param \Drupal\Core\Url $url
 *   The url.
 * @param ?\Drupal\node\NodeInterface|\Drupal\Core\Entity\EntityInterface[] $entities
 *   The entities.
 * @param array<\Drupal\Core\Url> $urls
 *   The urls.
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
