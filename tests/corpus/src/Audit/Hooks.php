<?php

/**
 * @file
 * Hook implementations the signature checks look at.
 */

declare(strict_types=1);

namespace Drupal\corpus\Audit;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Right and wrong hook signatures side by side.
 */
final class Hooks {

  /**
   * A well formed form alter.
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
  }

  /**
   * A well formed form-id specific alter, the form id left off.
   */
  #[Hook('form_node_form_alter')]
  public function nodeFormAlter(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * Not by reference and wrong second type.
   */
  // @mago-expect analysis:drupal/hook-form-alter-signature
  #[Hook('form_alter')]
  public function brokenFormAlter(array $form, array $form_state): void {
  }

  /**
   * Untyped parameters are only checked for the reference.
   */
  #[Hook('form_user_form_alter')]
  public function untypedFormAlter(&$form, $form_state): void {
  }

  /**
   * Fewer parameters than the module handler passes is fine.
   */
  #[Hook('form_alter')]
  public function shortFormAlter(array &$form): void {
  }

  /**
   * The form id is a string.
   */
  // @mago-expect analysis:drupal/hook-form-alter-signature
  #[Hook('form_alter')]
  public function intFormIdAlter(array &$form, FormStateInterface $form_state, int $form_id): void {
  }

  /**
   * A required fourth parameter is never passed.
   */
  // @mago-expect analysis:drupal/hook-form-alter-signature
  #[Hook('form_alter')]
  public function longFormAlter(array &$form, FormStateInterface $form_state, string $form_id, int $extra): void {
  }

  /**
   * An optional fourth parameter is harmless.
   */
  #[Hook('form_alter')]
  public function optionalExtraFormAlter(
    array &$form,
    FormStateInterface $form_state,
    string $form_id,
    int $extra = 0,
  ): void {
  }

  /**
   * A nullable array is still an array.
   */
  #[Hook('form_alter')]
  public function nullableFormAlter(?array &$form, FormStateInterface $form_state): void {
  }

  /**
   * Carries the cacheability parameter.
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $entity, CacheableMetadata $cacheability): array {
    return [];
  }

  /**
   * Misses the cacheability parameter.
   */
  // @mago-expect analysis:drupal/hook-entity-operation-cacheability
  #[Hook('entity_operation')]
  public function oldEntityOperation(EntityInterface $entity): array {
    return [];
  }

  /**
   * Alter without cacheability at position three.
   */
  // @mago-expect analysis:drupal/hook-entity-operation-cacheability
  #[Hook('entity_operation_alter')]
  public function oldEntityOperationAlter(array &$operations, EntityInterface $entity): void {
  }

  /**
   * Implements a deprecated hook.
   */
  // @mago-expect analysis:drupal/deprecated-hook
  #[Hook('old_thing')]
  public function oldThing(): void {
  }

  /**
   * Implements the replacement.
   */
  #[Hook('new_thing')]
  public function newThing(): void {
  }

}

/**
 * A class-level hook attribute names the method, __invoke() by default.
 */
#[Hook('form_alter')]
final class InvokedFormAlter {

  /**
   * Not by reference.
   */
  // @mago-expect analysis:drupal/hook-form-alter-signature
  public function __invoke(array $form, FormStateInterface $form_state): void {
  }

}

/**
 * A class-level hook attribute with an explicit method.
 */
#[Hook('old_thing', method: 'run')]
final class NamedOldThing {

  /**
   * Implements the deprecated hook.
   */
  // @mago-expect analysis:drupal/deprecated-hook
  public function run(): void {
  }

}
