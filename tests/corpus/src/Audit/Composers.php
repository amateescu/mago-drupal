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
   * Static, so serialization never sees it.
   */
  private static int $count = 0;

  /**
   * Restorable.
   */
  protected readonly Thing $thing;

  public function __construct(Thing $thing) {
    $this->thing = $thing;
    self::$count++;
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
 * PHP copies the trait's properties into this class, so its private one is
 * lost on serialization like one declared here.
 */
// @mago-expect analysis:drupal/dependency-serialization-property
final class CrossUser extends FormBase {

  use CrossTrait;

  /**
   * Reads the trait's properties so they are not write-only.
   */
  public function summary(): string {
    return $this->sneaky . $this->traitStorage::class;
  }

}
