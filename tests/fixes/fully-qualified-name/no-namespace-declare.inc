<?php

declare(strict_types=1);

/**
 * @file
 * Include file.
 */

function declare_url(): string {
  return \Drupal\Core\Url::fromRoute('x')->toString();
}
