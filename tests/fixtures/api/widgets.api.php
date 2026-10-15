<?php

/**
 * @file
 * The field widget hooks that core documents since Drupal 10.
 */

/**
 * Alter a single widget element.
 */
function hook_field_widget_single_element_form_alter(array &$element, $form_state, array $context) {
}

/**
 * Alter a single widget element of one widget type.
 */
function hook_field_widget_single_element_WIDGET_TYPE_form_alter(array &$element, $form_state, array $context) {
}

/**
 * Alter a complete widget.
 */
function hook_field_widget_complete_form_alter(&$field_widget_complete_form, $form_state, $context) {
}

/**
 * Alter a complete widget of one widget type.
 */
function hook_field_widget_complete_WIDGET_TYPE_form_alter(&$field_widget_complete_form, $form_state, $context) {
}
