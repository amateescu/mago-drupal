<?php

namespace Drupal\example;

use Drupal\Core\Url;

/**
 * Throws and catches.
 *
 * @throws \Exception
 */
#[\Exception]
function references(\Exception $given): \Exception
{
    try {
        throw new \Exception('message');
    }
    catch (\Exception $caught) {
        $known = $caught instanceof \Exception;
    }

    $name = \Exception::class;
    $url = Url::fromRoute('example');

    return $given;
}
