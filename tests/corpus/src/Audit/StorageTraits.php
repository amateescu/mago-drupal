<?php

/**
 * @file
 * Storages kept in trait properties.
 */

declare(strict_types=1);

namespace Drupal\corpus\Audit;

use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Keeps a storage for the classes using it.
 */
trait StorageHoldingTrait {

  /**
   * The thing storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  // @mago-expect analysis:drupal/entity-storage-property
  protected $storage;

  /**
   * Reads the storage so it is not write-only.
   */
  public function hasFirstThing(): bool {
    return $this->storage->load(1) !== NULL;
  }

}

/**
 * Uses the storage trait outside any handler.
 */
final class StorageHoldingUser {

  use StorageHoldingTrait;

}

/**
 * Keeps the storages of an entity handler.
 */
trait HandlerStorageTrait {

  /**
   * The storage the entity type manager hands the handler.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * The storage of another entity type.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  // @mago-expect analysis:drupal/entity-storage-property
  protected $styleStorage;

  /**
   * Reads the storages so they are not write-only.
   */
  public function hasFirstHandled(): bool {
    return $this->storage->load(1) !== NULL && $this->styleStorage->load(1) !== NULL;
  }

}

/**
 * The only class using the handler trait is a handler.
 */
final class TraitStorageListBuilder extends EntityListBuilder {

  use HandlerStorageTrait;

}
