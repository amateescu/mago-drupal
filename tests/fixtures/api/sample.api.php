<?php

/**
 * @file
 * Hook documentation shapes the reader has to handle.
 */

/**
 * A plain hook with three parameters.
 */
function hook_form_alter(array &$form, \Drupal\Core\Form\FormStateInterface $form_state, $form_id) {
}

/**
 * Defaults holding commas do not add parameters.
 */
function hook_with_defaults($first, array $options = ['a', 'b'], $callback = strtolower(...)) {
}

/**
 * Takes nothing.
 */
function hook_bare(): void {}

/**
 * Old and gone.
 *
 * @deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use
 *   hook_bare() instead.
 *
 * @see https://www.drupal.org/node/1
 */
function hook_old($thing) {
}

/**
 * Not a hook, just a helper in the api file.
 */
function _api_helper() {
}

/**
 * A helper that is deprecated itself.
 *
 * @deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use
 *   hook_bare() instead.
 */
function _deprecated_helper() {
}

/**
 * A current hook documented right after the deprecated helper.
 */
function hook_after_helper() {
}

/**
 * Documented with a directive line between the docblock and the function.
 */
// phpcs:ignore Drupal.Commenting.FunctionComment.Missing
function hook_update_N(&$sandbox) {
}
