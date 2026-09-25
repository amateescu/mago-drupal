<?php

/**
 * @file
 * An entity handler that holds the storage of another entity type.
 */

declare(strict_types=1);

namespace Drupal\corpus\Audit;

use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Keeps its own storage, which is fine, and another one, which is not.
 */
final class HandlerStorageListBuilder extends EntityListBuilder {

  /**
   * The storage of another entity type.
   */
  // @mago-expect analysis:drupal/entity-storage-property
  protected EntityStorageInterface $styleStorage;

  /**
   * Takes its own storage and another one.
   */
  public function __construct(
    protected EntityStorageInterface $storage,
    // @mago-expect analysis:drupal/entity-storage-injection
    EntityStorageInterface $style_storage,
  ) {
    $this->styleStorage = $style_storage;
  }

  /**
   * Reads the storages so they are not write-only.
   */
  public function hasFirstStyle(): bool {
    return $this->storage->load(1) !== NULL && $this->styleStorage->load(1) !== NULL;
  }

}
