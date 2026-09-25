<?php

/**
 * @file
 * A storage that only a docblock names.
 */

declare(strict_types=1);

namespace Drupal\corpus\Audit;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Keeps a storage it documents but never imports.
 */
final class DocumentedStorage {

  /**
   * The thing storage, typed only by this docblock.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  // @mago-expect analysis:drupal/entity-storage-property
  protected $storage;

  /**
   * Fetches the storage once.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->storage = $entity_type_manager->getStorage('corpus_thing');
  }

  /**
   * Reads the storage so it is not write-only.
   */
  public function hasFirst(): bool {
    return $this->storage->load(1) !== NULL;
  }

}
