<?php

/**
 * @file
 * Hooks the corpus documents, read from disk like core's api.php files.
 */

/**
 * Respond to a form being built.
 */
function hook_form_alter(array &$form, \Drupal\Core\Form\FormStateInterface $form_state, string $form_id): void {}

/**
 * Declare operations for an entity.
 */
function hook_entity_operation(\Drupal\Core\Entity\EntityInterface $entity, \Drupal\Core\Cache\CacheableMetadata $cacheability): array
{
    return [];
}

/**
 * Alter operations for an entity.
 */
function hook_entity_operation_alter(array &$operations, \Drupal\Core\Entity\EntityInterface $entity, \Drupal\Core\Cache\CacheableMetadata $cacheability): void {}

/**
 * An old hook.
 *
 * @deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use
 *   hook_new_thing() instead.
 */
function hook_old_thing(): void {}

/**
 * The replacement hook.
 */
function hook_new_thing(): void {}

/**
 * An old hook that Drupal 13 removes.
 *
 * @deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use
 *   hook_new_thing() instead.
 */
function hook_later_thing(): void {}

/**
 * Another old hook, implemented from an install file.
 *
 * @deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use
 *   hook_new_thing() instead.
 */
function hook_older_thing(): void {}

/**
 * An old hook implemented behind a bare attribute.
 *
 * @deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use
 *   hook_new_thing() instead.
 */
function hook_oldest_thing(): void {}

/**
 * An old hook whose procedural implementation is a legacy shim.
 *
 * @deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use
 *   hook_new_thing() instead.
 */
function hook_legacy_thing(): void {}

/**
 * An old hook that a helper after the scan stop is named like.
 *
 * @deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use
 *   hook_new_thing() instead.
 */
function hook_stopped_thing(): void {}

/**
 * An old requirements hook with a procedural implementation kept for old core.
 *
 * @deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. Use
 *   hook_new_thing() instead.
 */
function hook_legacy_requirements_thing(): void {}

/**
 * Alter a search query with a tag, named with a placeholder.
 *
 * @deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use
 *   hook_new_thing() instead.
 */
function hook_search_query_TAG_alter(): void {}

/**
 * Respond to an entity of one type being viewed.
 */
function hook_ENTITY_TYPE_view(): void {}

/**
 * Alter the view of an entity of one type.
 */
function hook_ENTITY_TYPE_view_alter(): void {}

/**
 * Alter a single widget element.
 */
function hook_field_widget_single_element_WIDGET_TYPE_form_alter(): void {}

/**
 * Alter a widget, which every single element hook name matches too.
 *
 * @deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use
 *   hook_field_widget_single_element_WIDGET_TYPE_form_alter() instead.
 */
function hook_field_widget_WIDGET_TYPE_form_alter(): void {}
