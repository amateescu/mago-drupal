<?php

declare(strict_types=1);

namespace Drupal\corpus\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Attribute\Hook as DrupalHook;

/**
 * Exercises the hook name rule.
 */
class HookAttributeFixture {

  /**
   * Implements hook_cron().
   *
   * A hook name that starts with the prefix.
   */
  // @mago-expect lint:drupal/hook-attribute-name
  #[Hook('hook_cron')]
  public function singleQuoted(): void {
  }

  /**
   * Implements hook_cron().
   *
   * A double-quoted hook name.
   */
  // @mago-expect lint:drupal/hook-attribute-name
  #[Hook('hook_cron')]
  public function doubleQuoted(): void {
  }

  /**
   * Implements hook_cron().
   *
   * A named argument.
   */
  // @mago-expect lint:drupal/hook-attribute-name
  #[Hook(hook: 'hook_entity_insert', module: 'corpus')]
  public function named(): void {
  }

  /**
   * Implements hook_cron().
   *
   * An import under another name.
   */
  // @mago-expect lint:drupal/hook-attribute-name
  #[DrupalHook('hook_cron')]
  public function aliased(): void {
  }

  /**
   * Implements hook_cron().
   *
   * A fully qualified name.
   */
  // @mago-expect lint:drupal/hook-attribute-name
  #[\Drupal\Core\Hook\Attribute\Hook('hook_cron')]
  public function fullyQualified(): void {
  }

  /**
   * Implements hook_cron().
   *
   * The name is written in lower case. PHP does not read the case.
   */
  // @mago-expect lint:drupal/hook-attribute-name
  #[hook('hook_cron')]
  public function lowerCaseClass(): void {
  }

  /**
   * Implements hook_cron().
   *
   * A hook name that is built from two literals.
   */
  // @mago-expect lint:drupal/hook-attribute-name
  #[Hook('hook_' . 'cron')]
  public function concatenated(): void {
  }

  /**
   * Implements hook_cron().
   *
   * Both attributes in one group are checked.
   */
  // @mago-expect lint:drupal/hook-attribute-name(2)
  #[Hook('hook_theme'), Hook('hook_cron')]
  public function sharedGroup(): void {
  }

  /**
   * Implements hook_cron().
   *
   * The Hook attribute follows another attribute in its group.
   */
  // @mago-expect lint:drupal/hook-attribute-name
  #[Sample, Hook('hook_cron')]
  public function afterAnotherAttribute(): void {
  }

  /**
   * Implements hook_cron().
   *
   * A doubled prefix is one report.
   */
  // @mago-expect lint:drupal/hook-attribute-name
  #[Hook('hook_hook_cron')]
  public function doubled(): void {
  }

  /**
   * The attribute is on a parameter.
   */
  // @mago-expect lint:drupal/hook-attribute-name
  public function otherTargets(
    // @mago-expect analysis:invalid-attribute-target
    #[Hook('hook_form_alter')]
    string $parameter,
  ): void {
  }

  /**
   * Implements hook_cron().
   *
   * The rule skips a prefix that is not at the start.
   */
  #[Hook('cron')]
  public function noPrefix(): void {
  }

  /**
   * Implements hook_cron().
   *
   * The prefix alone is no hook name.
   */
  #[Hook('hook_')]
  public function prefixOnly(): void {
  }

  /**
   * Implements hook_cron().
   *
   * The match is case sensitive and starts at the first character.
   */
  #[Hook('Hook_cron')]
  public function otherCase(): void {
  }

  /**
   * Implements hook_cron().
   *
   * A space before the prefix.
   */
  #[Hook(' hook_cron')]
  public function leadingSpace(): void {
  }

  /**
   * Implements hook_cron().
   *
   * Another word before the prefix.
   */
  #[Hook('xhook_cron')]
  public function otherWord(): void {
  }

  /**
   * Implements hook_cron().
   *
   * The value is not the hook name.
   */
  #[Hook('cron', module: 'hook_corpus')]
  public function secondArgument(): void {
  }

  /**
   * Implements hook_cron().
   *
   * The hook name is not a literal.
   */
  #[Hook(self::CRON)]
  public function constantName(): void {
  }

  /**
   * Implements hook_cron().
   *
   * A string below the attribute is not read.
   */
  // @mago-expect analysis:too-few-arguments
  #[Hook]
  public function noArguments(): string {
    return 'hook_later';
  }

  /**
   * Another attribute holds the value.
   */
  #[Sample('hook_cron')]
  public function otherAttribute(): void {
  }

  public const CRON = 'cron';

}
