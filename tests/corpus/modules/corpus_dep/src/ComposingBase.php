<?php

/**
 * @file
 * A base class in another module that uses DependencySerializationTrait.
 */

declare(strict_types=1);

namespace Drupal\corpus_dep;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;

/**
 * Serialized with the trait, so its subclasses are too.
 */
abstract class ComposingBase
{
    use DependencySerializationTrait;
}
