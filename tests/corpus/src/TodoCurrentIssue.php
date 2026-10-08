<?php

/**
 * @file
 * Exercises drupal/todo-current-issue.
 *
 * The corpus runs as the merge request of issue 3456789, which the Justfile
 * and tests/CorpusPins.php set as the source branch name.
 */

declare(strict_types=1);

namespace Drupal\corpus;

// @mago-expect lint:drupal/todo-current-issue
/**
 * Leaves to-dos that link the issue being worked on.
 *
 * @todo Drop this before https://www.drupal.org/project/corpus/issues/3456789
 *   lands.
 */
function todo_current_issue(): void {
  // @mago-expect lint:drupal/todo-current-issue
  // @todo Remove once https://www.drupal.org/i/3456789 is fixed.
  echo 'one';
  // @mago-expect lint:drupal/todo-current-issue
  // @todo Read https://www.drupal.org/node/3456789 again.
  echo 'two';
}

/**
 * Links other issues, or the current one outside a to-do.
 *
 * @see https://www.drupal.org/i/3456789
 */
function todo_other_issue(): void {
  // @todo Follow up in https://www.drupal.org/i/3456790.
  echo 'three';
  // See https://www.drupal.org/i/3456789 for the background.
  echo 'four';
}
