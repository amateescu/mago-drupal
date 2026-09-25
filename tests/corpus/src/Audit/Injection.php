<?php

/**
 * @file
 * Dependency injection shapes the class checks look at.
 */

declare(strict_types=1);

namespace Drupal\corpus\Audit;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\corpus\Entity\CorpusThingStorage;
use Drupal\user\RoleStorageInterface;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;

/**
 * Holds storages and calls \Drupal statically, both discouraged.
 */
final class Injection implements ContainerInjectionInterface {

  /**
   * The injected storage.
   */
  // @mago-expect analysis:drupal/entity-storage-property
  private EntityStorageInterface $storage;

  /**
   * A concrete storage class is a storage too.
   */
  // @mago-expect analysis:drupal/entity-storage-property
  protected ?CorpusThingStorage $thingStorage = NULL;

  /**
   * A storage outside any `Entity` namespace, known by what it extends.
   */
  // @mago-expect analysis:drupal/entity-storage-property
  protected ?RoleStorageInterface $roles = NULL;

  /**
   * The manager is what should be injected.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Untyped, but documented as a storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  // @mago-expect analysis:drupal/entity-storage-property
  protected $legacyStorage;

  /**
   * Storage parameters are reported, other storages are fine.
   */
  public function __construct(
    // @mago-expect analysis:drupal/entity-storage-injection
    EntityStorageInterface $storage,
    EntityTypeManagerInterface $entityTypeManager,
    protected FileStorage $files,
    protected KeyValueStoreInterface $keyValue,
    protected SessionStorageInterface $session,
  ) {
    $this->storage = $storage;
    $this->entityTypeManager = $entityTypeManager;
    $this->legacyStorage = $entityTypeManager->getStorage('corpus_thing');
  }

  /**
   * A static method has nothing injected, so the container is the way in.
   */
  public static function fromNothing(): object {
    return \Drupal::service('corpus.thing');
  }

  /**
   * Reads the other stores so none is write-only.
   */
  public function stores(): array {
    return [$this->files, $this->keyValue, $this->session, $this->roles];
  }

  /**
   * Reaches for the container statically.
   */
  public function later(): void {
    // @mago-expect analysis:drupal/global-drupal-call
    \Drupal::service('corpus.thing');
    // @mago-expect analysis:drupal/global-drupal-call
    \Drupal::state()->get('x');
    $this->entityTypeManager->getStorage('corpus_thing');
    $this->storage->load(1);
    $this->thingStorage?->load(1);
    $this->legacyStorage->load(1);
  }

}
