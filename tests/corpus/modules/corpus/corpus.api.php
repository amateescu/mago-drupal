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
 * An old requirements hook with a procedural implementation kept for old core.
 *
 * @deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. Use
 *   hook_new_thing() instead.
 */
function hook_legacy_requirements_thing(): void {}
