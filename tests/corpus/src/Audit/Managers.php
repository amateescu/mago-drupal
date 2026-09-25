<?php

/**
 * @file
 * Plugin managers, list builders and config entity declarations.
 */

declare(strict_types=1);

namespace Drupal\corpus\Audit;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Sets up discovery the way core managers do.
 */
final class GoodManager extends DefaultPluginManager {

  /**
   * Wires discovery.
   */
  public function __construct(object $cache) {
    $this->alterInfo('corpus_good');
    $this->setCacheBackend($cache, 'corpus_good_plugins');
  }

}

/**
 * Forgets both.
 */
final class BareManager extends DefaultPluginManager {

  /**
   * Wires nothing.
   */
  // @mago-expect analysis:drupal/plugin-manager-alter-info
  // @mago-expect analysis:drupal/plugin-manager-cache-backend
  public function __construct(object $cache) {}

}

/**
 * Wires discovery for its children.
 */
abstract class WiredManagerBase extends DefaultPluginManager {

  /**
   * Wires discovery.
   */
  public function __construct(object $cache) {
    $this->alterInfo('corpus_wired');
    $this->setCacheBackend($cache, 'corpus_wired_plugins');
  }

}

/**
 * Leaves the wiring to its parent's constructor.
 */
final class WiredManager extends WiredManagerBase {

  /**
   * Passes the cache on.
   */
  public function __construct(object $cache) {
    parent::__construct($cache);
  }

}

/**
 * A name holding "test" is not a test.
 */
final class ContestManager extends DefaultPluginManager {

  /**
   * Wires the cache only.
   */
  // @mago-expect analysis:drupal/plugin-manager-alter-info
  public function __construct(object $cache) {
    $this->setCacheBackend($cache, 'corpus_contest_plugins');
  }

}

/**
 * Operations carry cacheability.
 */
final class GoodListBuilder extends EntityListBuilder {

  /**
   * A handler is handed its storage by the entity type manager and keeps it.
   */
  public function __construct(
    protected EntityStorageInterface $storage,
  ) {
    $this->storage->load(1);
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity, ?CacheableMetadata $cacheability = NULL): array {
    return parent::getOperations($entity, $cacheability);
  }

}

/**
 * Names the parameter core keeps commented out.
 */
final class NamedListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity, ?CacheableMetadata $cacheability = NULL): array {
    // @mago-expect analysis:invalid-named-argument
    // @mago-expect analysis:too-many-arguments
    return parent::getOperations($entity, cacheability: $cacheability);
  }

}

/**
 * Operations predate cacheability.
 */
final class OldListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  // @mago-expect analysis:drupal/list-builder-cacheability
  public function getOperations(EntityInterface $entity): array {
    return $this->getDefaultOperations($entity);
  }

  /**
   * {@inheritdoc}
   */
  // @mago-expect analysis:drupal/list-builder-cacheability
  protected function getDefaultOperations(EntityInterface $entity): array {
    return [];
  }

}

/**
 * Exports its properties explicitly.
 */
#[ConfigEntityType(id: 'corpus_exported', config_export: ['id', 'label'])]
final class ExportedSetting extends ConfigEntityBase {}

/**
 * Leaves export to reflection.
 */
// @mago-expect analysis:drupal/config-entity-export
#[ConfigEntityType(id: 'corpus_unexported')]
final class UnexportedSetting extends ConfigEntityBase {}
