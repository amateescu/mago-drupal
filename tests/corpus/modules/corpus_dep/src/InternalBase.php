<?php

/**
 * @file
 * A class another module must not extend.
 */

declare(strict_types=1);

namespace Drupal\corpus_dep;

/**
 * Marked internal like core's own internal base classes.
 *
 * @internal
 */
abstract class InternalBase
{
    /**
     * Something a subclass inherits.
     */
    public function inherited(): void {}
}

/**
 * Not internal, so extending it is fine.
 */
abstract class PublicBase {}

/**
 * Internal, but final classes cannot be extended and are not listed.
 *
 * @internal
 */
final class InternalLeaf {}
