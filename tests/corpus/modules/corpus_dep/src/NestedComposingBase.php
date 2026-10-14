<?php

/**
 * @file
 * A base class that composes DependencySerializationTrait through a trait.
 */

declare(strict_types=1);

namespace Drupal\corpus_dep;

/**
 * Serialized with the trait through another trait, so its subclasses are too.
 */
abstract class NestedComposingBase
{
    use NestedSerializationTrait;
}
