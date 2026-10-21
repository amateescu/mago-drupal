<?php

namespace Drupal\example;

use Drupal\Core\Link; $link = new Link(<<<EOT
  text
  EOT, new \Drupal\Core\Url('x'));
