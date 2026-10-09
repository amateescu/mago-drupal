<?php

declare(strict_types=1);

/**
 * @file
 * Include file.
 */

use Drupal\Core\Url;

function declare_url(): string {
  return Url::fromRoute('x')->toString();
}
