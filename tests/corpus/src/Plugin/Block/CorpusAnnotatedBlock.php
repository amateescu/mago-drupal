<?php

/**
 * @file
 * Block plugins declared with legacy docblock annotations.
 */

declare(strict_types=1);

namespace Drupal\corpus\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * A block on the current annotation shape.
 *
 * @Block(
 *   id = "corpus_annotated_block",
 *   admin_label = @Translation("Annotated block"),
 *   category = @Translation("Corpus"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *   },
 * )
 */
final class CorpusAnnotatedBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [];
  }

  /**
   * Only exists here, so a call to it proves the plugin class.
   */
  public function onlyOnAnnotatedBlock(): void {
  }

}

/**
 * A block still declaring its contexts under the removed key.
 *
 * @Block(
 *   id = "corpus_context_block",
 *   admin_label = @Translation("Context block"),
 *   context = {
 *     "node" = @ContextDefinition("entity:node"),
 *   },
 * )
 */
// @mago-expect analysis:drupal/plugin-annotation-context
final class CorpusContextBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [];
  }

}
