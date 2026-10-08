<?php

/**
 * @file
 * Hooks documented with an uppercase placeholder in their names.
 */

/**
 * Alter any thing query.
 */
function hook_thing_query_alter($query) {
}

/**
 * Alter a thing query with a tag.
 *
 * @deprecated in thing:1.2.0 and is removed from thing:2.0.0. Use the
 *   event instead.
 */
function hook_thing_query_TAG_alter($query) {
}

/**
 * Alter a query of one thing type.
 */
function hook_thing_TYPE_query_alter($query) {
}

/**
 * Alter the thing query every site runs, documented under its own name.
 */
function hook_thing_query_all_alter($query) {
}

/**
 * Alter one form.
 */
function hook_form_FORM_ID_alter(&$form, $form_state, $form_id) {
}

/**
 * Alter an old form, documented under its own name.
 *
 * @deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use
 *   hook_form_alter() instead.
 */
function hook_form_old_alter(&$form, $form_state, $form_id) {
}

/**
 * Alter a single widget element.
 */
function hook_field_widget_single_element_WIDGET_TYPE_form_alter(&$element, $form_state, $context) {
}

/**
 * Alter a widget.
 *
 * @deprecated in drupal:9.2.0 and is removed from drupal:10.0.0. Use
 *   hook_field_widget_single_element_WIDGET_TYPE_form_alter() instead.
 */
function hook_field_widget_WIDGET_TYPE_form_alter(&$element, $form_state, $context) {
}

/**
 * Alter a thing query by entity type and tag.
 *
 * @deprecated in thing:1.2.0 and is removed from thing:2.0.0. Use the
 *   event instead.
 */
function hook_thing_tag__ENTITY_TYPE__TAG_alter($query) {
}

/**
 * Alter the view of one entity type.
 */
function hook_ENTITY_TYPE_view_alter(&$build, $entity, $display) {
}
