<?php

/**
 * @file
 * A block declared through a subclass of the block attribute.
 */

declare(strict_types=1);

namespace Drupal\corpus\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\corpus\Attribute\SpecialBlock;

/**
 * Discovered by the block manager through the attribute's parent.
 */
#[SpecialBlock(id: 'corpus_special')]
final class CorpusSpecialBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [];
  }

  /**
   * Only exists here, so a call to it proves the plugin class.
   */
  public function onlyOnSpecialBlock(): void {
  }

}
