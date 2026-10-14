<?php

namespace Drupal\sample\Internal;

/**
 * An internal base.
 *
 * @internal
 */
abstract class MarkedBase {}

/**
 * Internal, with an attribute between the docblock and the class.
 *
 * @internal
 */
#[\Attribute]
class Attributed {}

/**
 * Internal, behind a multi-line attribute.
 *
 * @internal
 */
#[\Attribute(
    flags: \Attribute::TARGET_CLASS,
)]
class Spread {}

/**
 * Mentions the inline tag only, which documents a member, not the class.
 *
 * Handles the {@internal} state.
 */
class Inline {}

/**
 * Final classes cannot be extended.
 *
 * @internal
 */
final class Sealed {}

/**
 * Only a method is internal here.
 */
class Open
{
    /**
     * @internal
     */
    public function hidden(): void {}
}

/**
 * The word appears in prose, not as a tag.
 *
 * Handles the internal state machine.
 */
class Prose {}
