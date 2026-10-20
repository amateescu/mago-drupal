<?php

declare(strict_types=1);

namespace Drupal\corpus\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Exercises the hook method comment rule.
 */
class HookMethodComment {

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
  }

  // @mago-expect lint:drupal/hook-method-comment
  /**
   * Runs the hourly cleanup.
   */
  #[Hook('cron')]
  public function describedCron(): void {
  }

  // @mago-expect lint:drupal/hook-method-comment
  /**
   * Implements hook_help()
   */
  #[Hook('help')]
  public function help(): string {
    return '';
  }

  // @mago-expect lint:drupal/hook-method-comment
  /**
   * Implements hook_entity_insert().
   *
   * @param object $entity
   *   The entity.
   */
  #[Hook('entity_insert')]
  public function entityInsert(object $entity): void {
  }

  #[Hook('theme')]
  /**
   * Implements hook_theme().
   */
  public function theme(): array {
    return [];
  }

  /**
   * A method with no hook attribute needs no hook summary.
   */
  public function helper(): void {
  }

}
