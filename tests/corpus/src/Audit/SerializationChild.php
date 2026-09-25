<?php

/**
 * @file
 * A child of a core base that composes DependencySerializationTrait.
 */

declare(strict_types=1);

namespace Drupal\corpus\Audit;

use Drupal\Core\Form\FormBase;
use Drupal\corpus\Nested\Thing;

/**
 * Inherits the trait from FormBase.
 */
final class SerializationChild extends FormBase {

  /**
   * The parent's __wakeup() cannot write a child's readonly before PHP 8.4.
   */
  // @mago-expect analysis:drupal/dependency-serialization-property
  protected readonly Thing $late;

  /**
   * A scalar readonly never holds an object, so it serializes as is.
   */
  protected readonly int $count;

  /**
   * Private is invisible to the trait wherever it is declared.
   */
  // @mago-expect analysis:drupal/dependency-serialization-property
  private int $secret = 0;

  /**
   * Iterables may hold objects, so readonly is a problem here too.
   */
  // @mago-expect analysis:drupal/dependency-serialization-property
  protected readonly iterable $items;

  /**
   * A promoted private property is a private property.
   */
  public function __construct(
    Thing $thing,
    // @mago-expect analysis:drupal/dependency-serialization-property
    private readonly Thing $promoted,
  ) {
    $this->late = $thing;
    $this->count = 1;
    $this->items = [];
  }

  /**
   * Reads everything so nothing is write-only.
   */
  public function summary(): string {
    return $this->late::class . $this->count . $this->secret . $this->promoted::class . gettype($this->items);
  }

}
