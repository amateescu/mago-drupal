<?php

/**
 * @file
 * A plugin manager base another module declares.
 */

declare(strict_types=1);

namespace Drupal\corpus_dep;

use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Wires the alter hook and the cache for its subclasses.
 */
abstract class DepManagerBase extends DefaultPluginManager
{
    public function __construct(object $cache)
    {
        $this->alterInfo('corpus_dep');
        $this->setCacheBackend($cache, 'corpus_dep_plugins');
    }
}
