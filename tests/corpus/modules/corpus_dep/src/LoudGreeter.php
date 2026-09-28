<?php

/**
 * @file
 * A decorator another module declares for a corpus service.
 */

declare(strict_types=1);

namespace Drupal\corpus_dep;

use Drupal\corpus\Nested\Greeter;

/**
 * Decorates the corpus greeter while this module is enabled.
 */
final class LoudGreeter implements Greeter
{
    /**
     * Greets loudly.
     */
    public function greet(): string
    {
        return 'HELLO';
    }

    /**
     * Only this decorator can shout.
     */
    public function shout(): string
    {
        return 'HEY';
    }
}
