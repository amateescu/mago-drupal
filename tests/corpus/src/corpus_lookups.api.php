<?php

/**
 * @file
 * Hook documentation, whose examples use made-up ids and names.
 */

declare(strict_types=1);

use Drupal\Core\Block\BlockManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\corpus\Audit\AttributeCallbacks;

/**
 * Makes each lookup that is reported outside hook documentation.
 */
function hook_corpus_lookups(
  EntityTypeManagerInterface $entity_type_manager,
  BlockManager $block_manager,
  ModuleHandlerInterface $module_handler,
  Connection $database,
): array {
  \Drupal::service('corpus.nope');
  \Drupal::service('corpus.aliased');
  $entity_type_manager->getStorage('corpus_typo');
  $block_manager->createInstance('corpus_stale');
  $module_handler->loadInclude('corpus', 'inc', 'corpus.missing');
  \Drupal::config('corpus.setings');
  $database->startTransaction();

  return ['#pre_render' => [[AttributeCallbacks::class, 'plain']]];
}
