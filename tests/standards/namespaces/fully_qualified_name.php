<?php

namespace Drupal\example;

use Drupal\Core\Url;

// Reported.
$url = \Drupal\Core\Url::fromRoute('<front>');

// Fine.
$url = Url::fromRoute('<front>');
