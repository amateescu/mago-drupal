<?php

namespace Drupal\example;

use Drupal\Core\Url; // The url class.

function crlf(): Url
{
    return \Drupal\Core\Link::fromTextAndUrl('x', Url::fromRoute('y'))->getUrl();
}
