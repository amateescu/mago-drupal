<?php

namespace Drupal\example;

/**
 * Throws an exception when the value is empty.
 *
 * @throws \Exception
 */
function prose(string $value): void
{
    if ($value === '') {
        throw new \Exception('message');
    }
}
