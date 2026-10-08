<?php

/**
 * @file
 * A decorator sharing two unrelated interfaces with the service it decorates.
 */

declare(strict_types=1);

namespace Drupal\corpus_dep;

use Drupal\corpus\Nested\Greeter;
use Drupal\corpus\Nested\Waver;

/**
 * Decorates the corpus host while this module is enabled.
 */
final class LoudHost implements Greeter, Waver
{
    /**
     * Greets loudly.
     */
    public function greet(): string
    {
        return 'HELLO';
    }

    /**
     * Waves loudly.
     */
    public function wave(): string
    {
        return 'BYE';
    }
}
