<?php

/**
 * @file
 * Lookups in test code, which the checks leave alone.
 */

declare(strict_types=1);

namespace Drupal\Tests\corpus;

use Drupal\Core\Block\BlockManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\corpus\Audit\AttributeCallbacks;

/**
 * Makes each lookup that is reported outside tests.
 */
final class SkippedLookupsTest implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly BlockManager $blockManager,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly Connection $database,
  ) {}

  /**
   * Tests mock and fake what they look up, so nothing here is reported.
   */
  public function testLookups(): array {
    \Drupal::service('corpus.nope');
    \Drupal::service('corpus.aliased');
    $this->entityTypeManager->getStorage('corpus_typo');
    $this->blockManager->createInstance('corpus_stale');
    $this->moduleHandler->loadInclude('corpus', 'inc', 'corpus.missing');
    \Drupal::config('corpus.setings');
    $this->database->startTransaction();

    return ['#pre_render' => [[AttributeCallbacks::class, 'plain']]];
  }

}
