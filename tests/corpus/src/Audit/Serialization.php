<?php

/**
 * @file
 * Properties DependencySerializationTrait cannot restore.
 */

declare(strict_types=1);

namespace Drupal\corpus\Audit;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\corpus\Nested\Thing;

/**
 * Composes the trait itself.
 */
class Serialization {

  use DependencySerializationTrait;

  /**
   * Invisible to the trait's __wakeup().
   */
  // @mago-expect analysis:drupal/dependency-serialization-property
  private Thing $hidden;

  /**
   * Restorable.
   */
  protected Thing $visible;

  /**
   * Readonly is fine in the class that composes the trait.
   */
  public function __construct(
    protected readonly Thing $own,
  ) {
    $this->hidden = $own;
    $this->visible = $own;
  }

  /**
   * Reads both so nothing is write-only.
   */
  public function things(): array {
    return [$this->hidden, $this->visible, $this->own];
  }

}
