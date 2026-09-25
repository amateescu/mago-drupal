<?php

/**
 * @file
 * Fluent setters core documents on an interface and implements in a trait.
 */

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\Core\Cache\RefinableCacheableDependencyTrait;

/**
 * Exercises the receiver handed back for a trait's fluent setter.
 */
final class Fluent {

  use RefinableCacheableDependencyTrait;

  /**
   * The chain keeps the object, so the call after it resolves.
   */
  public function chain(): void {
    $this->addCacheTags(['corpus'])->rest();
  }

  /**
   * Named so only this class can answer the second call in the chain.
   */
  public function rest(): void {
  }

}
