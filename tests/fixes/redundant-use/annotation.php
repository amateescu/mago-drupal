<?php

namespace Drupal\example;

use Exception;

/**
 * Names the class in an annotation.
 *
 * @Exception("label")
 */
function annotation(): Exception
{
    return new Exception('message');
}
