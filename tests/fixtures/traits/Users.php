<?php

namespace Drupal\fixture;

use Drupal\fixture\Serializing as Restorable;

/**
 * Uses the trait by its name.
 */
class Direct
{
    use Serializing;
}

/**
 * Uses the trait through a trait.
 */
trait Nested
{
    use Serializing;
}

/**
 * Uses the trait that uses the trait.
 */
class ThroughNested
{
    use Nested;
}

/**
 * Uses the trait under an imported alias.
 */
class Aliased
{
    use Restorable;
}

/**
 * Only names the trait.
 */
class Mentions
{
    public function name(): string
    {
        return Serializing::class;
    }
}
