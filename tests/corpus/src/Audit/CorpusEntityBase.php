<?php

declare(strict_types=1);

// Entities are built without the container, so static calls are fine there.
namespace Drupal\corpus\Audit;

use Drupal\Core\Entity\ContentEntityBase;

/**
 * An entity that talks to the container statically.
 */
abstract class CorpusEntityBase extends ContentEntityBase {

  /**
   * Allowed: entities cannot inject.
   */
  public function owner(): void {
    \Drupal::service('corpus.thing');
  }

}
