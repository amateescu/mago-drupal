<?php

namespace Drupal\example;

use Exception;

/**
 * Names the class by its short name in a docblock type.
 *
 * @throws Exception
 */
function documented(): void
{
    throw new Exception('message');
}
