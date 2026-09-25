<?php

/**
 * @file
 * The other half of the duplicate id.
 */

declare(strict_types=1);

namespace Drupal\corpus\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;

/**
 * Collides with CorpusDuplicateBlock on purpose.
 */
#[Block(id: 'corpus_duplicate')]
final class CorpusDuplicateTwinBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [];
  }

}
