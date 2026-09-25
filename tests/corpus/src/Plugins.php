<?php

/**
 * @file
 * Plugin instantiation typed through the plugin index.
 */

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\Core\Block\BlockManager;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Condition\ConditionManager;
use Drupal\Core\Render\ElementInfoManager;
use Drupal\corpus\Plugin\CorpusBlockManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates block plugins through core's manager and a subclass of it.
 */
final class Plugins {

  public function __construct(
    private readonly BlockManager $blockManager,
    private readonly BlockManagerInterface $blockManagerInterface,
    private readonly CorpusBlockManager $corpusBlockManager,
    private readonly ConditionManager $conditionManager,
    private readonly ContainerInterface $container,
    private readonly ElementInfoManager $elementInfo,
  ) {}

  /**
   * A literal id resolves to the class carrying the manager's attribute.
   */
  public function known(): void {
    $this->blockManager->createInstance('corpus_block')->onlyOnCorpusBlock();
    $this->blockManager->createInstance('corpus_derived:anything')->onlyOnDerivedBlock();
    $this->corpusBlockManager->createInstance('corpus_block')->onlyOnCorpusBlock();
    $this->blockManagerInterface->createInstance('corpus_block')->onlyOnCorpusBlock();
    $this->blockManager->createInstance(plugin_id: 'corpus_block')->onlyOnCorpusBlock();
    // A subclass of the block attribute is still a block.
    $this->blockManager->createInstance('corpus_special')->onlyOnSpecialBlock();
    // A legacy annotation declares the plugin as well as an attribute does.
    $this->blockManager->createInstance('corpus_annotated_block')->onlyOnAnnotatedBlock();
    $this->blockManager->createInstance('corpus_documented')->onlyOnDocumentedBlock();
    $this->blockManager->createInstance('corpus_migrating')->onlyOnMigratingBlock();
    $this->elementInfo->createInstance('corpus_element')->onlyOnCorpusElement();
    // The manager fetched from the container carries its class too.
    $this->container->get('plugin.manager.block')->createInstance('corpus_block')->onlyOnCorpusBlock();
    \Drupal::service('plugin.manager.block')->createInstance('corpus_block')->onlyOnCorpusBlock();
    // @mago-expect analysis:non-existent-method
    $this->blockManager->createInstance('corpus_block')->missing();
  }

  /**
   * Two classes claiming one id leave it untyped, and it is not unknown.
   */
  public function duplicate(): void {
    // @mago-expect analysis:ambiguous-object-method-access
    $this->blockManager->createInstance('corpus_duplicate')->build();
    // @mago-expect analysis:ambiguous-object-method-access
    $this->blockManager->createInstance('corpus_annotated_twin')->build();
  }

  /**
   * Annotations that do not count.
   *
   * A stale one next to the attribute, a code sample, and one on an
   * abstract base.
   */
  public function ignoredAnnotations(): void {
    // @mago-expect analysis:drupal/unknown-plugin
    $this->blockManager->createInstance('corpus_stale')->onlyOnBroken();
    // @mago-expect analysis:drupal/unknown-plugin
    $this->blockManager->createInstance('corpus_doc_sample')->onlyOnBroken();
    // @mago-expect analysis:drupal/unknown-plugin
    $this->blockManager->createInstance('corpus_abstract_annotated')->onlyOnBroken();
  }

  /**
   * An id nothing declares is reported and typed as the fallback block.
   */
  public function unknown(string $id): void {
    // @mago-expect analysis:drupal/unknown-plugin
    $this->blockManager->createInstance('corpus_typo')->onlyOnBroken();
    // Derivative ids come from a deriver and are not checked.
    $this->blockManager->createInstance('corpus_typo:derivative')->onlyOnBroken();
    // @mago-expect analysis:ambiguous-object-method-access
    $this->blockManager->createInstance($id)->onlyOnCorpusBlock();
    // No condition plugin is scanned, so nothing can be said about ids.
    // @mago-expect analysis:ambiguous-object-method-access
    $this->conditionManager->createInstance('corpus_condition')->onlyOnCorpusBlock();
  }

}
