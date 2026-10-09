<?php

/**
 * Returns a url.
 */
function function_docblock_url(): string {
  return \Drupal\Core\Url::fromRoute('x')->toString();
}
