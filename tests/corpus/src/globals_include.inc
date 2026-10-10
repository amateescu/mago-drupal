<?php

/**
 * @file
 * Exercises drupal/global-variable in an .inc file.
 */

declare(strict_types=1);

/**
 * Declares globals in an include file.
 */
function globals_include_declare(): void {
  global $_globals_include_owned, $user;
  // @mago-expect lint:drupal/global-variable
  global $globals_include_unprefixed;

  $_globals_include_owned = [];
  $globals_include_unprefixed = [];
}
