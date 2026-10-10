<?php

/**
 * @file
 * Exercises drupal/form-alter-comment in an .inc file.
 */

declare(strict_types=1);

// An .inc file takes the module name from the nearest info file, here
// corpus.info.yml, as in Coder.
// @mago-expect lint:drupal/form-alter-comment
/**
 * Implements hook_form_alter().
 */
function form_alter_include(array &$form): void {
}

/**
 * Implements hook_form_alter().
 */
function corpus_form_alter(array &$form): void {
}
