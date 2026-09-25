<?php

/**
 * @file
 * Constructors that reach for the container.
 */

declare(strict_types=1);

namespace Drupal\corpus\Audit;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\corpus\Nested\Thing;

/**
 * Falls back to the container for a service old callers do not pass.
 */
final class InjectionFallback implements ContainerInjectionInterface {

  /**
   * The injected service.
   */
  protected Thing $thing;

  /**
   * Keeps old callers working, the way Drupal's deprecation policy asks.
   */
  public function __construct(?Thing $thing = NULL) {
    if ($thing === NULL) {
      @trigger_error(
        'Calling '
        . __METHOD__
        . '() without the $thing argument is deprecated in drupal:11.4.0 and it will be required in drupal:12.0.0. See https://www.drupal.org/node/3567619',
        E_USER_DEPRECATED,
      );
      $thing = \Drupal::service('corpus.thing');
    }

    $this->thing = $thing;
  }

  /**
   * Reads the property so it is not write-only.
   */
  public function thing(): Thing {
    return $this->thing;
  }

}

/**
 * Reaches for the container although every caller could inject.
 */
final class InjectionInConstructor implements ContainerInjectionInterface {

  /**
   * The looked-up service.
   */
  protected Thing $thing;

  /**
   * A constructor without an optional service is still checked.
   */
  public function __construct() {
    // @mago-expect analysis:drupal/global-drupal-call
    $this->thing = \Drupal::service('corpus.thing');
  }

  /**
   * Reads the property so it is not write-only.
   */
  public function thing(): Thing {
    return $this->thing;
  }

}
