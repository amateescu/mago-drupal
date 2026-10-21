<?php

namespace Drupal\example;

use /* The route. */ Drupal\Core\Url;

/**
 * Takes an imported type.
 *
 * @param Url $url
 *   The url.
 */
function comment($url)
{
    return $url;
}
