<?php

/**
 * @file
 * A block plugin whose ids come from a deriver.
 */

declare(strict_types=1);

namespace Drupal\corpus\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;

/**
 * One class behind every `corpus_derived:*` id.
 */
#[Block(id: 'corpus_derived', deriver: 'Drupal\corpus\Plugin\Derivative\CorpusDeriver')]
final class CorpusDerivedBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [];
  }

  /**
   * Only exists here, so a call to it proves the plugin class.
   */
  public function onlyOnDerivedBlock(): void {
  }

}
