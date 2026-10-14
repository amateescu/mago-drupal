<?php

declare(strict_types=1);

// An entity handler that holds the storage of another entity type.
namespace Drupal\corpus\Audit;

use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;

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

/**
 * Passes its own storage on under another name, then takes another one.
 */
final class RenamedStorageListBuilder extends EntityListBuilder {

  /**
   * The storage of another entity type.
   */
  // @mago-expect analysis:drupal/entity-storage-property
  protected EntityStorageInterface $actionStorage;

  /**
   * Takes the storage the parent takes, whatever its name, and one more.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $entity_storage,
    // @mago-expect analysis:drupal/entity-storage-injection
    EntityStorageInterface $action_storage,
  ) {
    parent::__construct($entity_type, $entity_storage);
    $this->actionStorage = $action_storage;
  }

  /**
   * Reads the other storage so it is not write-only.
   */
  public function hasFirstAction(): bool {
    return $this->actionStorage->load(1) !== NULL;
  }

}
