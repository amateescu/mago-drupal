<?php

namespace Drupal\example;

use Drupal\Core\Url;

function late_import(): Url
{
    return new \Drupal\Core\Link\Thing();
}

use Drupal\Core\Render\Markup;
