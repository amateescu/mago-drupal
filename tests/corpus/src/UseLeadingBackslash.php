<?php

/**
 * @file
 * Imports whose first name starts with a backslash.
 */

declare(strict_types=1);

namespace Drupal\corpus\Imports;

// @mago-expect lint:drupal/use-leading-backslash
use \Drupal\corpus\Nested\Thing as RootedThing;
// @mago-expect lint:drupal/use-leading-backslash
use function \Drupal\corpus\rooted_helper;
// @mago-expect lint:drupal/use-leading-backslash
use const \Drupal\corpus\ROOTED_LIMIT;
// @mago-expect lint:drupal/use-leading-backslash
use function \Drupal\corpus\{grouped_one, grouped_two};
// @mago-expect lint:drupal/use-leading-backslash
use /* A comment does not hide the name. */ \Drupal\corpus\Nested\Commented;
// @mago-expect lint:drupal/use-leading-backslash
use FUNCTION \Drupal\corpus\upper_helper;
// Only the first name of a statement is checked, as in Coder.
use Drupal\corpus\Nested\Second, \Drupal\corpus\Nested\Third;
