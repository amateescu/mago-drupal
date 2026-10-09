<?php

namespace Drupal\example;

use Drupal\Core\Url;
use Drupal\node\NodeInterface as Node;
use Drupal\Core\Entity\EntityInterface;

/**
 * Takes imported types.
 *
 * @param Url $url
 *   The url.
 * @param ?Node|EntityInterface[] $entities
 *   The entities.
 * @param array<Url> $urls
 *   The urls.
 *
 * @return Url|null
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
