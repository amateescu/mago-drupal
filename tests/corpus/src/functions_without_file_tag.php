<?php

// @mago-expect lint:drupal/file-comment
/**
 * Holds functions in a .php file, which Coder checks like a .module file.
 */

declare(strict_types=1);

namespace Drupal\corpus;

/**
 * Does nothing.
 */
function functions_without_file_tag_helper(): void {
}
