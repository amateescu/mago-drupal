<?php

/**
 * @file
 * Core values typed the way the code behaves rather than as documented.
 */

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\Component\Plugin\Derivative\DeriverInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityTypeEventSubscriberTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Extension\ModuleUninstallValidatorInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\corpus\Entity\CorpusSetting;
use Drupal\corpus\Entity\CorpusStringItemInterface;
use Drupal\corpus\Entity\CorpusThing;
use Drupal\corpus\Entity\CorpusThingStorage;
use Drupal\corpus\Nested\Thing;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Reads the values the plugin types more closely than core documents them.
 */
final class CoreTypes {

  /**
   * A config entity ID is a string, so only its null is reported.
   */
  public function configEntityId(CorpusSetting $setting): int {
    // @mago-expect analysis:possibly-null-argument
    return strlen($setting->id());
  }

  /**
   * A content entity with a string ID field has a string ID.
   */
  public function stringId(CorpusStringItemInterface $item): int {
    // @mago-expect analysis:possibly-null-argument
    return strlen($item->id());
  }

  /**
   * Any other content entity keeps core's integer or string.
   */
  public function thingId(CorpusThing $thing): int {
    // @mago-expect analysis:possibly-invalid-argument
    // @mago-expect analysis:possibly-null-argument
    return strlen($thing->id());
  }

  /**
   * A handler created from a class name is an instance of that class.
   */
  public function handler(EntityTypeManagerInterface $manager, EntityTypeInterface $type): CorpusThingStorage {
    return $manager->createHandlerInstance(CorpusThingStorage::class, $type);
  }

  /**
   * A list of revision IDs may mix integers and strings.
   *
   * @return array<int|string, \Drupal\Core\Entity\EntityInterface>
   *   The revisions.
   */
  public function revisions(RevisionableStorageInterface $storage, int $first, string $second): array {
    return $storage->loadMultipleRevisions([$first, $second]);
  }

  /**
   * A claimed queue item has its data, ID and creation time.
   */
  public function queueItem(QueueInterface $queue): mixed {
    $item = $queue->claimItem();
    return $item === FALSE ? NULL : $item->data;
  }

  /**
   * A scanned file has its URI, filename and name.
   *
   * @return list<string>
   *   The URIs.
   */
  public function scannedFiles(FileSystemInterface $file_system): array {
    $uris = [];
    foreach ($file_system->scanDirectory('public://', '/.*/') as $file) {
      $uris[] = $file->uri;
    }
    return $uris;
  }

  /**
   * An uninstall reason is markup, so the cast is needed.
   */
  public function firstReason(ModuleInstallerInterface $installer): string {
    return (string) ($installer->validateUninstall(['corpus'])['corpus'][0] ?? '');
  }

}

/**
 * Returns translatable markup, as core's own validators do.
 */
final class CorpusUninstallValidator implements ModuleUninstallValidatorInterface {

  /**
   * {@inheritdoc}
   */
  public function validate($module) {
    return [new TranslatableMarkup('There is content.')];
  }

}

/**
 * Subscribes with the event list the trait builds.
 */
final class CorpusEntityTypeSubscriber implements EventSubscriberInterface {

  use EntityTypeEventSubscriberTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return static::getEntityTypeEvents();
  }

}

/**
 * Derives plugins from the array definition it is handed.
 */
final class CorpusDeriver implements DeriverInterface {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    return ['one' => ['label' => $base_plugin_definition['label']]];
  }

}

/**
 * Sets a service on the instance its parent's factory creates.
 */
final class CorpusEntityForm extends ContentEntityForm {

  /**
   * The service the factory sets.
   */
  protected ?Thing $thing = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->thing = $container->get('corpus.thing');
    return $instance;
  }

  /**
   * Reads the property so it is not write-only.
   */
  public function thing(): ?Thing {
    return $this->thing;
  }

}
