<?php

namespace Drupal\example;

use Drupal\Core\Url;

/**
 * Takes an imported type inside generics, a shape and a callable.
 *
 * @param array<string, Url> $urls
 *   The urls, keyed by name.
 * @param array{url: ?Url, urls: list<Url>} $item
 *   The item.
 * @param callable(Url $url): void $visit
 *   Visits a url.
 *
 * @return array<int, list<Url>>
 *   The urls, grouped.
 */
function generics($urls, $item, $visit): array
{
    return [];
}
