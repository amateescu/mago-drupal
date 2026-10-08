<?php

/**
 * @file
 * A child of a core base that composes DependencySerializationTrait.
 */

declare(strict_types=1);

namespace Drupal\corpus\Audit;

use Drupal\Core\Form\FormBase;
use Drupal\corpus\Nested\Thing;
use Drupal\corpus_dep\ComposingBase;
use Drupal\corpus_dep\NestedComposingBase;
use Drupal\corpus_dep\NestedSerializationTrait;

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

/**
 * Inherits the trait from a class another module declares.
 */
final class ComposingChild extends ComposingBase {

  /**
   * Private is invisible to the trait wherever it is declared.
   */
  // @mago-expect analysis:drupal/dependency-serialization-property
  private int $hidden = 0;

  /**
   * Reads the property so it is not unused.
   */
  public function hidden(): int {
    return $this->hidden;
  }

}

/**
 * Composes the trait through a trait that uses it.
 */
final class NestedComposer {

  use NestedSerializationTrait;

  /**
   * Restorable, since the trait's methods run in this class's scope.
   */
  protected readonly Thing $own;

  /**
   * A subclass's __sleep() would have to name this mangled.
   */
  // @mago-expect analysis:drupal/dependency-serialization-property
  private int $secret = 0;

  public function __construct(Thing $own) {
    $this->own = $own;
  }

  /**
   * Reads everything so nothing is write-only.
   */
  public function summary(): string {
    return $this->own::class . $this->secret;
  }

}

/**
 * Inherits the trait from a class that composes it through another trait.
 */
final class NestedComposingChild extends NestedComposingBase {

  /**
   * The parent's __wakeup() cannot write a child's readonly before PHP 8.4.
   */
  // @mago-expect analysis:drupal/dependency-serialization-property
  protected readonly Thing $late;

  /**
   * Private is invisible to the trait wherever it is declared.
   */
  // @mago-expect analysis:drupal/dependency-serialization-property
  private int $hidden = 0;

  public function __construct(Thing $late) {
    $this->late = $late;
  }

  /**
   * Reads everything so nothing is write-only.
   */
  public function summary(): string {
    return $this->late::class . $this->hidden;
  }

}
