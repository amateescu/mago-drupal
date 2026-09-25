<?php

/**
 * @file
 * Trait composition shapes that must not be reported.
 */

declare(strict_types=1);

namespace Drupal\corpus\Audit;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormBase;
use Drupal\corpus\Nested\CrossTrait;
use Drupal\corpus\Nested\Thing;

/**
 * Composes the trait again although FormBase already does.
 *
 * Its own readonly properties are restorable, since the trait runs in this
 * class's scope.
 */
final class RedundantForm extends FormBase {

  use DependencySerializationTrait;

  /**
   * Restorable.
   */
  protected readonly Thing $thing;

  public function __construct(Thing $thing) {
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
 * Uses a trait declared in another file.
 *
 * That trait's private and storage properties belong to the trait, not to
 * this class.
 */
final class CrossUser extends FormBase {

  use CrossTrait;

  /**
   * Reads the trait's properties so they are not write-only.
   */
  public function summary(): string {
    return $this->sneaky . $this->traitStorage::class;
  }

}
