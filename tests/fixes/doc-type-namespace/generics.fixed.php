<?php

namespace Drupal\example;

/**
 * Takes an imported type inside generics, a shape and a callable.
 *
 * @param array<string, \Drupal\Core\Url> $urls
 *   The urls, keyed by name.
 * @param array{url: ?\Drupal\Core\Url, urls: list<\Drupal\Core\Url>} $item
 *   The item.
 * @param callable(\Drupal\Core\Url $url): void $visit
 *   Visits a url.
 *
 * @return array<int, list<\Drupal\Core\Url>>
 *   The urls, grouped.
 */
function generics($urls, $item, $visit): array
{
    return [];
}
