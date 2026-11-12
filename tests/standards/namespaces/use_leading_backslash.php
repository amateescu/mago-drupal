<?php

namespace Drupal\example;

// Reported.
use \Drupal\Core\Url;

$url = Url::fromRoute('<front>');

// Fine.
use Drupal\Core\Url;

$url = Url::fromRoute('<front>');
