<?php

// @mago-expect lint:drupal/file-comment
/**
 * @file
 * Sits above the namespace of a file that holds one class.
 */

declare(strict_types=1);

namespace Drupal\corpus;

/**
 * Is the only declaration of the file.
 */
final class NamespacedFileDocblock {}
