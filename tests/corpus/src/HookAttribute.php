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
   * A hook name that starts with the prefix.
   */
  // @mago-expect lint:drupal/hook-attribute-name
  #[Hook('hook_cron')]
  public function singleQuoted(): void {
  }

  /**
   * A double-quoted hook name.
   */
  // @mago-expect lint:drupal/hook-attribute-name
  #[Hook('hook_cron')]
  public function doubleQuoted(): void {
  }

  /**
   * A named argument.
   */
  // @mago-expect lint:drupal/hook-attribute-name
  #[Hook(hook: 'hook_entity_insert', module: 'corpus')]
  public function named(): void {
  }

  /**
   * An import under another name.
   */
  // @mago-expect lint:drupal/hook-attribute-name
  #[DrupalHook('hook_cron')]
  public function aliased(): void {
  }

  /**
   * A fully qualified name.
   */
  // @mago-expect lint:drupal/hook-attribute-name
  #[\Drupal\Core\Hook\Attribute\Hook('hook_cron')]
  public function fullyQualified(): void {
  }

  /**
   * The name is written in lower case. PHP does not read the case.
   */
  // @mago-expect lint:drupal/hook-attribute-name
  #[hook('hook_cron')]
  public function lowerCaseClass(): void {
  }

  /**
   * A hook name that is built from two literals.
   */
  // @mago-expect lint:drupal/hook-attribute-name
  #[Hook('hook_' . 'cron')]
  public function concatenated(): void {
  }

  /**
   * Both attributes in one group are checked.
   */
  // @mago-expect lint:drupal/hook-attribute-name(2)
  #[Hook('hook_theme'), Hook('hook_cron')]
  public function sharedGroup(): void {
  }

  /**
   * The Hook attribute follows another attribute in its group.
   */
  // @mago-expect lint:drupal/hook-attribute-name
  #[Sample, Hook('hook_cron')]
  public function afterAnotherAttribute(): void {
  }

  /**
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
    #[Hook('hook_form_alter')]
    string $parameter,
  ): void {
  }

  /**
   * The rule skips a prefix that is not at the start.
   */
  #[Hook('cron')]
  public function noPrefix(): void {
  }

  /**
   * The prefix alone is no hook name.
   */
  #[Hook('hook_')]
  public function prefixOnly(): void {
  }

  /**
   * The match is case sensitive and starts at the first character.
   */
  #[Hook('Hook_cron')]
  public function otherCase(): void {
  }

  /**
   * A space before the prefix.
   */
  #[Hook(' hook_cron')]
  public function leadingSpace(): void {
  }

  /**
   * Another word before the prefix.
   */
  #[Hook('xhook_cron')]
  public function otherWord(): void {
  }

  /**
   * The value is not the hook name.
   */
  #[Hook('cron', module: 'hook_corpus')]
  public function secondArgument(): void {
  }

  /**
   * The hook name is not a literal.
   */
  #[Hook(self::CRON)]
  public function constantName(): void {
  }

  /**
   * A string below the attribute is not read.
   */
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
