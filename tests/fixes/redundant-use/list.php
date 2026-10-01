<?php

namespace Drupal\example;

use Drupal\Core\Url, Throwable, Drupal\Core\Link;

function list_import(Throwable $error): Url
{
    return Link::createFromRoute('x', 'y')->getUrl();
}
