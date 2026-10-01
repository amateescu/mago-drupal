<?php

namespace Drupal\example;

use Exception as Failure;

function alias(): Failure
{
    return new Failure('message');
}
