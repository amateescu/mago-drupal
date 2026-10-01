<?php

namespace Drupal\example;

use Drupal;
use Drupal\Core\Url;
use function Drupal\Core\helper;

function other_imports(): Url
{
    return Drupal::service('url_generator')->generateFromRoute('x');
}
