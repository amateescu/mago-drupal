<?php

/**
 * @file
 * Core values typed the way the code behaves rather than as documented.
 */

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\Component\Assertion\Inspector;
use Drupal\Component\Plugin\Derivative\DeriverInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityTypeEventSubscriberTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Extension\ModuleUninstallValidatorInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\corpus\Entity\CorpusNamedSetting;
use Drupal\corpus\Entity\CorpusSetting;
use Drupal\corpus\Entity\CorpusStringItemInterface;
use Drupal\corpus\Entity\CorpusThing;
use Drupal\corpus\Entity\CorpusThingStorage;
use Drupal\corpus\Nested\Thing;
use Drupal\user\PermissionHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Reads the values the plugin types more closely than core documents them.
 */
final class CoreTypes {

  /**
   * A config entity ID is a string, so only its null is reported.
   *
   * The class inherits `id()` from `EntityBase`, as core's config entities do.
   */
  public function configEntityId(CorpusSetting $setting): int {
    // @mago-expect analysis:possibly-null-argument
    return strlen($setting->id());
  }

  /**
   * An override that declares a narrower type keeps it.
   */
  public function namedSettingId(CorpusNamedSetting $setting): int {
    return strlen($setting->id());
  }

  /**
   * The same on a config entity typed by its interface.
   */
  public function configInterfaceId(ConfigEntityInterface $entity): int {
    // @mago-expect analysis:possibly-null-argument
    return strlen($entity->id());
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
   * A claimed queue item is a stdClass, as core's queues build it.
   */
  public function queueItemClass(QueueInterface $queue): int|string|null {
    $item = $queue->claimItem();
    return $item instanceof \stdClass ? $item->item_id : NULL;
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
   * The scanned files may be documented as the stdClass they are.
   */
  public function scannedFileObjects(FileSystemInterface $file_system): int {
    /** @var \stdClass[] $files */
    $files = $file_system->scanDirectory('public://', '/.*/');
    return count($files);
  }

  /**
   * An uninstall reason is markup, so the cast is needed.
   */
  public function firstReason(ModuleInstallerInterface $installer): string {
    return (string) ($installer->validateUninstall(['corpus'])['corpus'][0] ?? '');
  }

  /**
   * An Inspector assertion narrows the value it checks.
   *
   * @return iterable<mixed, string>
   *   The names.
   */
  public function inspectedStrings(mixed $names): iterable {
    assert(Inspector::assertAllStrings($names));

    return $names;
  }

  /**
   * An assertion the type already holds is redundant.
   *
   * @param list<string> $names
   *   The names.
   */
  public function inspectedAgain(array $names): void {
    // @mago-expect analysis:redundant-type-comparison
    assert(Inspector::assertAllStrings($names));
  }

  /**
   * Numeric strings count, since is_numeric() takes them.
   *
   * @return iterable<mixed, numeric>
   *   The amounts.
   */
  public function inspectedNumbers(mixed $amounts): iterable {
    assert(Inspector::assertAllNumeric($amounts));

    return $amounts;
  }

  /**
   * A strict array has the keys 0 to n-1 in order, so it is a list.
   *
   * @return list<mixed>
   *   The values.
   */
  public function inspectedList(mixed $values): array {
    assert(Inspector::assertStrictArray($values));

    return $values;
  }

  /**
   * The objects are of the classes named.
   *
   * @return iterable<mixed, \Drupal\corpus\Nested\Thing>
   *   The things.
   */
  public function inspectedObjects(mixed $things): iterable {
    assert(Inspector::assertAllObjects($things, Thing::class));

    return $things;
  }

  /**
   * Any callable can be called with arguments.
   */
  public function inspectedCallables(mixed $callbacks): void {
    assert(Inspector::assertAllCallable($callbacks));
    foreach ($callbacks as $callback) {
      $callback('argument');
    }
  }

  /**
   * A callable of any kind only says a mixed value is iterable.
   *
   * @param mixed $values
   *   The values.
   * @param array<string, int> $weights
   *   The weights.
   *
   * @return iterable<mixed, mixed>
   *   The values.
   */
  public function inspectedAll(mixed $values, array $weights): iterable {
    assert(Inspector::assertAll('is_int', $weights));
    assert(Inspector::assertAll('is_string', $values));

    return $values;
  }

  /**
   * A permission's title is translated markup, or what a callback returned.
   */
  public function permissionTitle(PermissionHandlerInterface $permissions): string {
    $title = $permissions->getPermissions()['administer corpus']['title'];

    return $title instanceof TranslatableMarkup ? $title->render() : $title;
  }

  /**
   * A URL renders to a string unless asked for its bubbleable metadata.
   */
  public function urlString(Url $url): string {
    return $url->toString();
  }

  /**
   * Asking for the metadata gives a GeneratedUrl.
   */
  public function generatedUrl(Url $url): GeneratedUrl {
    return $url->toString(TRUE);
  }

  /**
   * Without the argument, the result is no GeneratedUrl.
   */
  public function urlIsNotGenerated(Url $url): GeneratedUrl {
    // @mago-expect analysis:invalid-return-statement
    return $url->toString();
  }

  /**
   * A class extending Url keeps every member of it, and the narrowing.
   */
  public function urlSubclass(CorpusUrl $url): GeneratedUrl {
    return $url->getRouteName() === '' ? $url->toString(collect_bubbleable_metadata: TRUE) : $url->toString(TRUE);
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

/**
 * A URL class of the module's own.
 */
final class CorpusUrl extends Url {}
