<?php

namespace Drupal\example;

use Legacy;

function relative(): Legacy\Item
{
    return new Legacy\Item(new Legacy());
}
