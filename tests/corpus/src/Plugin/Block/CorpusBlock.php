<?php

/**
 * @file
 * A block plugin declared with an attribute in a scanned file.
 */

declare(strict_types=1);

namespace Drupal\corpus\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;

/**
 * The corpus block.
 */
#[Block(id: 'corpus_block')]
final class CorpusBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [];
  }

  /**
   * Only exists here, so a call to it proves the plugin class.
   */
  public function onlyOnCorpusBlock(): void {
  }

}
