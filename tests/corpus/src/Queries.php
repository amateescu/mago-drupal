<?php

/**
 * @file
 * Entity queries typed through the tags on the query object.
 */

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Builds queries with and without access checking.
 */
final class Queries {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * A checked query returns ids keyed by entity or revision id.
   *
   * @return array<int|string, string>
   */
  public function checked(): array {
    return $this->entityTypeManager
      ->getStorage('corpus_thing')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('id', 1)
      ->execute();
  }

  /**
   * The static helper carries the same tags, and a count is never negative.
   *
   * @return int<0, max>
   */
  public function counted(): int {
    return \Drupal::entityQuery('corpus_thing')->accessCheck()->count()->execute();
  }

  /**
   * The order of accessCheck() and count() does not matter.
   */
  public function countedFirst(): int {
    return \Drupal::entityQuery('corpus_thing')->count()->accessCheck()->execute();
  }

  /**
   * Bypassing access on purpose is allowed.
   *
   * @return array<int|string, string>
   */
  public function bypassed(): array {
    return \Drupal::entityQuery('corpus_thing')->accessCheck(FALSE)->execute();
  }

  /**
   * A content entity query without accessCheck() throws at runtime.
   */
  public function unchecked(): void {
    // @mago-expect analysis:drupal/entity-query-access-check
    \Drupal::entityQuery('corpus_thing')->condition('id', 1)->execute();
    $query = $this->entityTypeManager->getStorage('corpus_thing')->getQuery();
    $query->sort('id');
    // @mago-expect analysis:drupal/entity-query-access-check
    $query->execute();
    // @mago-expect analysis:drupal/entity-query-access-check
    \Drupal::entityQuery('corpus_thing')->count()->execute();
  }

  /**
   * Standalone accessCheck() and count() statements retag the variable.
   */
  public function statements(): int {
    $query = $this->entityTypeManager->getStorage('corpus_thing')->getQuery();
    $query->condition('id', 1);
    $query->accessCheck(FALSE);
    $query->count();

    return $query->execute();
  }

  /**
   * Unknown entity types are tracked and checked.
   *
   * Only a known config entity type is exempt from the check.
   */
  public function unknownEntityType(
    string $id,
    EntityStorageInterface $bare,
    ConfigEntityStorageInterface $config,
  ): void {
    // @mago-expect analysis:drupal/entity-query-access-check
    \Drupal::entityQuery($id)->execute();
    // @mago-expect analysis:drupal/entity-query-access-check
    $bare->getQuery()->execute();
    $config->getQuery()->execute();
    // @mago-expect analysis:drupal/entity-query-access-check
    \Drupal::entityQuery('corpus_missing')->execute();
    \Drupal::entityQuery($id)->accessCheck()->execute();
  }

  /**
   * Whichever branch of a union skipped the check is reported.
   */
  public function union(bool $flag): void {
    $query = $flag
      ? \Drupal::entityQuery('corpus_thing')->accessCheck()
      : \Drupal::entityQuery('corpus_thing');
    // @mago-expect analysis:drupal/entity-query-access-check
    $query->execute();
  }

  /**
   * Condition groups return to the query without losing its tags.
   *
   * @return array<int|string, string>
   */
  public function grouped(): array {
    $query = $this->entityTypeManager->getStorage('corpus_thing')->getQuery();
    $group = $query->orConditionGroup()->condition('id', 1)->condition('id', 2);

    return $query->condition($group)->latestRevision()->accessCheck()->execute();
  }

  /**
   * Config entities have no access checking to call.
   *
   * Their ids come back as strings keyed by strings.
   *
   * @return array<string, string>
   */
  public function config(): array {
    return \Drupal::entityQuery('corpus_setting')->execute();
  }

  /**
   * Aggregates return grouped rows from either entry point.
   *
   * @return list<array<string, mixed>>
   */
  public function aggregate(): array {
    $rows = \Drupal::entityQueryAggregate('corpus_thing')->accessCheck()->groupBy('type')->execute();
    $configRows = \Drupal::entityQueryAggregate('corpus_setting')->groupBy('type')->execute();
    $storageRows = $this->entityTypeManager->getStorage('corpus_thing')->getAggregateQuery()->accessCheck()->execute();

    return [...$rows, ...$configRows, ...$storageRows];
  }

  /**
   * The result shapes are exact enough to reject a wrong consumer.
   */
  public function shapes(): void {
    // @mago-expect analysis:invalid-argument
    $this->requireInt(\Drupal::entityQuery('corpus_missing')->accessCheck()->execute());
    // @mago-expect analysis:invalid-argument
    $this->requireIntKeys(\Drupal::entityQuery('corpus_setting')->execute());
  }

  /**
   * A query that may be counting or listing returns either.
   */
  public function countOrList(bool $count): void {
    $query = \Drupal::entityQuery('corpus_thing')->accessCheck();
    if ($count) {
      $query->count();
    }

    // @mago-expect analysis:possibly-invalid-argument
    $this->requireInt($query->execute());
  }

  /**
   * Stands in for a consumer that needs an int.
   */
  private function requireInt(int $count): void {
  }

  /**
   * Stands in for a consumer that needs integer keys.
   *
   * @param array<int, string> $ids
   *   Ids keyed by integer.
   */
  private function requireIntKeys(array $ids): void {
  }

}
