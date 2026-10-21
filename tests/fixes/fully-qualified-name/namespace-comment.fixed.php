<?php

namespace /* The module. */ Drupal\example;

use Drupal\Core\Url;

/**
 * Names a class of the same namespace and one of another.
 */
function namespace_comment(): string
{
    return Helper::name() . Url::fromRoute('x')->toString();
}
