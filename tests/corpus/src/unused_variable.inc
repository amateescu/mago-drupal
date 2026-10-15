<?php

/**
 * @file
 * Exercises drupal/unused-variable at the top level of a file.
 */

declare(strict_types=1);

// @mago-expect lint:drupal/unused-variable
$unused_variable_top = 1;

$unused_variable_read = 2;
unused_variable_take($unused_variable_read);

if (PHP_SAPI === 'cli') {
  // @mago-expect lint:drupal/unused-variable
  $unused_variable_in_if = 3;
}

// The value of a `$key => $value` foreach may stay unused.
foreach ([1, 2] as $unused_variable_key => $unused_variable_value) {
  unused_variable_take($unused_variable_key);
}

/**
 * Takes values.
 */
function unused_variable_take(mixed ...$values): void {
}

/**
 * Reads a global, which is a scope apart from the top level.
 */
function unused_variable_global(): void {
  global $_unused_variable_top;

  unused_variable_take($_unused_variable_top);
}

/**
 * Declares a function inside a function, which is a scope of its own.
 */
function unused_variable_outer(): void {
  /**
   * Writes a local.
   */
  function unused_variable_inner(): void {
    // @mago-expect lint:drupal/unused-variable
    $inner = 1;
  }
}

/**
 * Reports a write before an include, which Coder reports too.
 */
function unused_variable_include(): void {
  // @mago-expect lint:drupal/unused-variable
  $variables = [];
  include __DIR__ . '/unused_variable.tpl.php';
}
