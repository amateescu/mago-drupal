<?php

/**
 * @file
 * Variables that their function declares twice.
 */

declare(strict_types=1);

namespace Drupal\corpus;

/**
 * A parameter redeclared as a static variable.
 */
function redeclaration_static(int $count): int {
  // @mago-expect lint:drupal/variable-redeclaration
  static $count;

  return \is_int($count) ? $count : 0;
}

/**
 * A parameter redeclared as a global variable.
 */
function redeclaration_global(array $_corpus_settings): mixed {
  // @mago-expect lint:drupal/variable-redeclaration
  global $_corpus_settings;

  return $_corpus_settings;
}

/**
 * A variable assigned in one branch and declared global in another.
 */
function redeclaration_branch(bool $local): mixed {
  if ($local) {
    $_corpus_base = 'x';
  }
  else {
    // @mago-expect lint:drupal/variable-redeclaration
    global $_corpus_base;
  }

  return $_corpus_base;
}

/**
 * A parameter reused as the catch variable.
 */
function redeclaration_catch(\Exception $error): string {
  try {
    throw new \RuntimeException('Failed.');
  }
  // @mago-expect lint:drupal/variable-redeclaration
  catch (\RuntimeException $error) {
  }

  return $error->getMessage();
}

/**
 * A parameter rebound by reference.
 */
function redeclaration_reference(array &$elements, array &$form): void {
  if ($elements === []) {
    // @mago-expect lint:drupal/variable-redeclaration
    $elements = &$form;
  }
}

/**
 * The same global and static declarations twice.
 */
function redeclaration_twice(): string {
  global $_corpus_name;
  // @mago-expect lint:drupal/variable-redeclaration
  global $_corpus_name;
  static $calls;
  // @mago-expect lint:drupal/variable-redeclaration
  static $calls;
  $calls = 1;

  return \gettype($_corpus_name) . $calls;
}

/**
 * A closure's use list that repeats a parameter, which PHP rejects.
 */
function redeclaration_closure(int $step): \Closure {
  // @mago-expect lint:drupal/variable-redeclaration
  return function (int $step) use ($step): int {
    return $step;
  };
}

/**
 * The declarations that may take the name of a plain variable.
 */
function redeclaration_allowed(array &$items, int $limit): string {
  $error = NULL;
  try {
    throw new \RuntimeException('Failed.');
  }
  catch (\RuntimeException $error) {
  }

  $list = [];
  $list = &$items;
  // Coder treats a static variable with a value as an assignment.
  static $limit = 10;

  return $error->getMessage() . count($list) . $limit;
}

/**
 * A read doesn't declare the name.
 */
function redeclaration_read(): bool {
  $set = isset($_corpus_later);
  global $_corpus_later;

  return $set && $_corpus_later !== NULL;
}
