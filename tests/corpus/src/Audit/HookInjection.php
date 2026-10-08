<?php

/**
 * @file
 * Hook classes that reach for the container.
 */

declare(strict_types=1);

namespace Drupal\corpus\Audit;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\corpus\Nested\Thing;

/**
 * Implements a hook through a method, so Drupal autowires the class.
 */
final class HookInjection {

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    // @mago-expect analysis:drupal/global-drupal-call
    \Drupal::service('corpus.thing')->onlyOnThing();
  }

  /**
   * Any other method of the class can use an injected service too.
   */
  public function thing(): Thing {
    // @mago-expect analysis:drupal/global-drupal-call
    return \Drupal::service('corpus.thing');
  }

  /**
   * A service older core lacks is looked up only on the path for newer core.
   */
  public function compatible(): mixed {
    return DeprecationHelper::backwardsCompatibleCall(
      \Drupal::VERSION,
      '11.4',
      static fn (): Thing => \Drupal::service('corpus.thing'),
      static fn (): ?Thing => NULL,
    );
  }

  /**
   * Code on its way out keeps its lookup.
   *
   * @deprecated in corpus:1.0.0 and is removed from corpus:2.0.0. Use
   *   thing() instead.
   *
   * @see https://www.drupal.org/node/1
   */
  public function oldThing(): Thing {
    return \Drupal::service('corpus.thing');
  }

  /**
   * A static method has no injected service to use.
   */
  public static function staticThing(): Thing {
    return \Drupal::service('corpus.thing');
  }

}

/**
 * Implements a hook through the class itself, with __invoke().
 */
#[Hook('cron')]
final class InvokedHookInjection {

  /**
   * Runs the hook.
   */
  public function __invoke(): void {
    // @mago-expect analysis:drupal/global-drupal-call
    \Drupal::service('corpus.thing')->onlyOnThing();
  }

}

/**
 * Carries an attribute, but no hook, so nothing injects its services.
 */
final class AttributeWithoutHook {

  /**
   * Returns the service.
   */
  #[\ReturnTypeWillChange]
  public function thing(): Thing {
    return \Drupal::service('corpus.thing');
  }

}
