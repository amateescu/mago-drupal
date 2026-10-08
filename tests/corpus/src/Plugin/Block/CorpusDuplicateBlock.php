<?php

declare(strict_types=1);

// A second class claiming an id another block already declares.
namespace Drupal\corpus\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;

/**
 * Collides with CorpusBlock on purpose; neither class may be handed back.
 */
#[Block(id: 'corpus_duplicate')]
final class CorpusDuplicateBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [];
  }

}
