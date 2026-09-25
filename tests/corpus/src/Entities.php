<?php

/**
 * @file
 * Entity API lookups typed through the entity type index.
 *
 * A method that only exists on the resolved class proves the type; a missing
 * method proves the receiver is no longer the declared interface.
 */

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\corpus\Entity\CorpusSetting;
use Drupal\corpus\Entity\CorpusThing;
use Drupal\corpus\Entity\CorpusThingStorage;
use Drupal\corpus\Entity\CorpusThingStorageInterface;

/**
 * Exercises the entity type manager, storages and the repository.
 */
final class Entities {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityRepositoryInterface $entityRepository,
  ) {}

  /**
   * Handlers named in the attribute resolve to their classes.
   */
  public function handlers(): void {
    $this->entityTypeManager->getStorage('corpus_thing')->onlyOnThingStorage();
    $this->entityTypeManager->getAccessControlHandler('corpus_thing')->onlyOnThingAccess();
    $this->entityTypeManager->getFormObject('corpus_thing', 'default')->onlyOnThingForm();
    $this->entityTypeManager->getHandler('corpus_thing', 'access')->onlyOnThingAccess();
    // @mago-expect analysis:non-existent-method
    $this->entityTypeManager->getStorage('corpus_thing')->missing();
  }

  /**
   * The `$return_as_object` argument picks the access result type.
   */
  public function access(EntityInterface $entity): void {
    $handler = $this->entityTypeManager->getAccessControlHandler('corpus_thing');
    $handler->access($entity, 'view', NULL, TRUE)->onlyOnAccessResult();
    $handler->createAccess('corpus_thing', NULL, [], TRUE)->onlyOnAccessResult();
    // @mago-expect analysis:invalid-method-access
    $handler->access($entity, 'view')->onlyOnAccessResult();
  }

  /**
   * A storage typed by its interface still names its entity type.
   */
  public function storageInterface(CorpusThingStorageInterface $storage): void {
    $storage->load(1)?->onlyOnThing();
    // @mago-expect analysis:drupal/entity-query-access-check
    $storage->getQuery()->execute();
  }

  /**
   * A legacy annotation declares handlers the same way.
   */
  public function annotated(): void {
    $this->entityTypeManager->getStorage('corpus_legacy')->onlyOnLegacyStorage();
    $this->entityTypeManager->getStorage('corpus_legacy')->load(1)?->onlyOnLegacy();
    $this->entityTypeManager->getFormObject('corpus_legacy', 'default')->onlyOnThingForm();
    $this->entityTypeManager->getStorage('corpus_legacy_setting')->onlyOnConfigStorage();
    $this->entityTypeManager->getStorage('corpus_legacy_setting')->load('a')?->onlyOnLegacySetting();
    // @mago-expect analysis:non-existent-method
    $this->entityTypeManager->getStorage('corpus_legacy')->missing();
  }

  /**
   * Handlers the attribute leaves out get core's defaults, per kind.
   */
  public function defaults(): void {
    $this->entityTypeManager->getStorage('corpus_setting')->onlyOnConfigStorage();
    $this->entityTypeManager->getAccessControlHandler('corpus_setting')->onlyOnDefaultAccess();
    $this->entityTypeManager->getViewBuilder('corpus_thing')->onlyOnDefaultViewBuilder();
    // Config entity types have no default view builder or list builder.
    // @mago-expect analysis:non-existent-method
    $this->entityTypeManager->getViewBuilder('corpus_setting')->onlyOnDefaultViewBuilder();
    // @mago-expect analysis:non-existent-method
    $this->entityTypeManager->getListBuilder('corpus_thing')->missing();
  }

  /**
   * Every getter reports an unknown id, except a probe that accepts null.
   */
  public function unknownType(): void {
    // @mago-expect analysis:drupal/unknown-entity-type
    $this->entityTypeManager->getStorage('corpus_typo');
    // @mago-expect analysis:drupal/unknown-entity-type
    $this->entityTypeManager->getHandler('corpus_typo', 'access');
    // @mago-expect analysis:drupal/unknown-entity-type
    $this->entityTypeManager->getDefinition('corpus_typo');
    $this->entityTypeManager->getDefinition('corpus_typo', FALSE);
  }

  /**
   * A named argument fills its parameter wherever it is written.
   */
  public function namedArguments(): void {
    // The handler type comes first here, and it is not an entity type id.
    $this->entityTypeManager->getHandler(handler_type: 'access', entity_type_id: 'corpus_thing');
    // @mago-expect analysis:drupal/unknown-entity-type
    $this->entityTypeManager->getHandler(handler_type: 'access', entity_type_id: 'corpus_typo');
    // @mago-expect analysis:drupal/unknown-entity-type
    $this->entityTypeManager->getStorage(entity_type_id: 'corpus_typo');
    // Still a probe, so still nothing to report.
    $this->entityTypeManager->getDefinition(exception_on_invalid: FALSE, entity_type_id: 'corpus_typo');
  }

  /**
   * A handler class Mago has never seen keeps the declared interface.
   */
  public function phantom(): void {
    // @mago-expect analysis:non-existent-method
    $this->entityTypeManager->getStorage('corpus_bare')->missing();
    $this->entityTypeManager->getAccessControlHandler('corpus_bare')->onlyOnDefaultAccess();
  }

  /**
   * A storage knows its entity class through the id it was fetched with.
   */
  public function storage(): void {
    $storage = $this->entityTypeManager->getStorage('corpus_thing');
    $storage->load(1)?->onlyOnThing();
    $storage->loadUnchanged(1)?->onlyOnThing();
    $storage->loadRevision(1)?->onlyOnThing();
    $storage->loadRevisionUnchanged(1)?->onlyOnThing();
    $storage->create()->onlyOnThing();
    foreach ($storage->loadMultipleRevisions([1]) as $revision) {
      $revision->onlyOnThing();
    }

    foreach ($storage->loadMultiple([1, 2]) as $thing) {
      $thing->onlyOnThing();
    }

    foreach ($storage->loadByProperties(['id' => 1]) as $thing) {
      $thing->onlyOnThing();
    }

    // The default config storage is shared, so the id tag does the work.
    $this->entityTypeManager->getStorage('corpus_setting')->create()->onlyOnSetting();
  }

  /**
   * An untagged storage resolves only when its class serves one entity type.
   */
  public function uniqueStorage(EntityStorageInterface $untagged): void {
    // @mago-expect analysis:non-existent-method
    $untagged->load(1)?->onlyOnThing();
    $this->thingStorage()->create()->onlyOnThing();
  }

  /**
   * An untagged storage in the union could load any entity type.
   */
  public function partlyTaggedReceiver(bool $flag, string $id): void {
    $storage = $flag
      ? $this->entityTypeManager->getStorage('corpus_thing')
      : $this->entityTypeManager->getStorage($id);
    // @mago-expect analysis:non-existent-method
    $storage->load(1)?->onlyOnThing();
  }

  /**
   * A spread could pass `$return_as_object`, so the flag is unknown.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check access to.
   * @param list<bool> $flags
   *   The flags to pass on.
   */
  public function spreadFlag(EntityInterface $entity, array $flags): void {
    $handler = $this->entityTypeManager->getAccessControlHandler('corpus_thing');
    // @mago-expect analysis:possibly-invalid-argument
    $this->requireBool($handler->access($entity, 'view', NULL, ...$flags));
  }

  /**
   * An argument that may be anything keeps the declared return type.
   */
  public function translationOfAnything(mixed $thing): void {
    // @mago-expect analysis:mixed-argument
    $this->entityRepository->getTranslationFromContext($thing)->label();
  }

  /**
   * A receiver that may be either of two storages resolves to neither.
   */
  public function unionReceiver(bool $flag): void {
    $storage = $flag
      ? $this->entityTypeManager->getStorage('corpus_thing')
      : $this->entityTypeManager->getStorage('corpus_setting');
    $storage->create()->label();
    // @mago-expect analysis:non-existent-method
    $storage->create()->onlyOnSetting();
  }

  /**
   * The repository types by the entity type id argument.
   */
  public function repository(CorpusThing $thing): void {
    $this->entityRepository->loadEntityByUuid('corpus_thing', 'uuid')?->onlyOnThing();
    $this->entityRepository->loadEntityByConfigTarget('corpus_setting', 'target')?->onlyOnSetting();
    $this->entityRepository->getActive('corpus_thing', 1)?->onlyOnThing();
    $this->entityRepository->getCanonical('corpus_thing', 1)?->onlyOnThing();
    $this->entityRepository->getTranslationFromContext($thing)->onlyOnThing();
    foreach ($this->entityRepository->getActiveMultiple('corpus_thing', [1]) as $active) {
      $active->onlyOnThing();
    }

    foreach ($this->entityRepository->getCanonicalMultiple('corpus_setting', [1]) as $canonical) {
      $canonical->onlyOnSetting();
    }
  }

  /**
   * Unknown ids keep the declared interface types and are reported.
   */
  public function unknown(): void {
    // @mago-expect analysis:drupal/unknown-entity-type
    // @mago-expect analysis:non-existent-method
    $this->entityTypeManager->getStorage('corpus_missing')->onlyOnThingStorage();
    // @mago-expect analysis:non-existent-method
    $this->entityRepository->loadEntityByUuid('corpus_missing', 'uuid')?->onlyOnThing();
  }

  /**
   * Narrows the definition to the content or config interface.
   */
  public function definition(): ContentEntityTypeInterface {
    return $this->entityTypeManager->getDefinition('corpus_thing');
  }

  /**
   * A config entity type narrows to the config interface.
   */
  public function configDefinition(): ConfigEntityTypeInterface {
    return $this->entityTypeManager->getDefinition('corpus_setting');
  }

  /**
   * Asking not to throw keeps null.
   *
   * A bare entity type keeps the declared type.
   */
  public function optionalDefinition(): ?EntityTypeInterface {
    // @mago-expect analysis:possibly-null-argument
    $this->requireDefinition($this->entityTypeManager->getDefinition('corpus_thing', FALSE));

    return $this->entityTypeManager->getDefinition('corpus_bare');
  }

  /**
   * Stands in for a consumer that needs a definition.
   */
  private function requireDefinition(EntityTypeInterface $definition): void {
  }

  /**
   * Takes a boolean, so a wider type shows up as an argument error.
   */
  private function requireBool(bool $allowed): void {
  }

  /**
   * Returns the thing storage under its declared class only.
   */
  private function thingStorage(): CorpusThingStorage {
    throw new \RuntimeException('fixture');
  }

}
