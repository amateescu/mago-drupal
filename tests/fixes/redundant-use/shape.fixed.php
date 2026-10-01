<?php

namespace Drupal\example;

use ArrayObject;

/**
 * Names the class inside a shape spread over several lines.
 *
 * @param array{
 *   items: ArrayObject,
 * } $options
 *   The options.
 */
function shape(array $options): ArrayObject
{
    return new ArrayObject($options);
}
