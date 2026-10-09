<?php

use Drupal\Core\Url;

/**
 * Returns a url.
 */
function function_docblock_url(): string {
  return Url::fromRoute('x')->toString();
}
