<?php

namespace Drupal\other;

/**
 * A trait of the same short name in another namespace.
 */
trait Serializing
{
}

/**
 * Uses the other trait.
 */
class Elsewhere
{
    use Serializing;
}
