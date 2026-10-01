<?php

namespace Drupal\example;

use Drupal\Core\Url; // The url class.
use Drupal\Core\Link;

function crlf(): Url
{
    return Link::fromTextAndUrl('x', Url::fromRoute('y'))->getUrl();
}
