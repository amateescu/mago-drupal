<?php

/**
 * @file
 * An include in a subdirectory, returning by reference.
 */

function &Foo_Admin_Validate(array &$form, $form_state) {
  static $result = [];
  return $result;
}
