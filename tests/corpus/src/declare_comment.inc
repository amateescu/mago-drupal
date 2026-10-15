<?php

/**
 * @file
 * Has the expectation comment right above the declare statement.
 */

// @mago-expect lint:drupal/file-comment
declare(strict_types=1);

/**
 * Stands in for the code below the declare statement.
 */
function declare_comment_help(): string {
  return '';
}
