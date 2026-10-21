<?php

namespace /* The module. */ Drupal\example;

/**
 * Names a class of the same namespace and one of another.
 */
function namespace_comment(): string
{
    return \Drupal\example\Helper::name() . \Drupal\Core\Url::fromRoute('x')->toString();
}
