<?php

/**
 * @file
 * A trait that composes DependencySerializationTrait for its users.
 */

declare(strict_types=1);

namespace Drupal\corpus_dep;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;

/**
 * Brings DependencySerializationTrait into every class that uses it.
 */
trait NestedSerializationTrait
{
    use DependencySerializationTrait;
}
