<?php

namespace Drupal\example;

use /* The base class. */ Exception;

function comment(): Exception
{
    return new Exception('message');
}
