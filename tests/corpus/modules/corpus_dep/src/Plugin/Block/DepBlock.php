<?php

/**
 * @file
 * A block another module declares with a legacy annotation.
 */

declare(strict_types=1);

namespace Drupal\corpus_dep\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Declared by annotation in a module the corpus only includes.
 *
 * @Block(
 *   id = "corpus_dep_block",
 *   admin_label = @Translation("Dependency block"),
 * )
 */
final class DepBlock extends BlockBase
{
    /**
     * Builds nothing.
     */
    public function build(): array
    {
        return [];
    }

    /**
     * Only exists here, so a call to it proves the plugin type.
     */
    public function onlyOnDepBlock(): void {}
}
