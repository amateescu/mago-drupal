<?php

namespace Drupal\example;

use \ReflectionClass;

function leading_backslash(): ReflectionClass
{
    return new ReflectionClass(self::class);
}
