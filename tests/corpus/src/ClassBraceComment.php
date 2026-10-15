<?php

declare(strict_types=1);

namespace Drupal\corpus;

/**
 * A `//` comment after the opening brace of a class.
 */
// @mago-expect lint:drupal/post-statement-comment
class BraceLineComment { // Holds the count.

  /**
   * The count.
   */
  public int $count = 0;

}

/**
 * A block comment after the opening brace of an interface.
 */
// @mago-expect lint:drupal/post-statement-comment
interface BraceBlockCommentInterface { /* Counts things. */

  /**
   * Counts the things.
   */
  public function count(): int;

}

/**
 * A comment after the opening brace of a trait with an empty body.
 */
// @mago-expect lint:drupal/post-statement-comment
trait BraceEmptyTrait { /* Nothing yet. */
}

/**
 * A comment after the opening brace of an enum.
 */
// @mago-expect lint:drupal/post-statement-comment
enum BraceEnum: string { // The states.

  case On = 'on';

}

/**
 * Builds an object of an anonymous class.
 */
function brace_anonymous_class(): object {
  // @mago-expect lint:drupal/post-statement-comment
  return new class { /* Holds nothing. */

    /**
     * The value.
     */
    public int $value = 0;

  };
}

/**
 * A directive after the opening brace, which is not reported.
 */
class BraceDirective { // phpcs:ignore Drupal.Classes.ClassDeclaration

  /**
   * The values.
   */
  public array $values = [];

}

/**
 * An empty class, which is not reported.
 */
class BraceEmpty {}

/**
 * A comment on its own line below the opening brace, which is not reported.
 */
class BraceOwnLine {

  // Holds the count.
  /**
   * The count.
   */
  public int $count = 0;

}

/**
 * A block comment after a statement, which is not reported.
 */
function brace_block_after_statement(): int {
  $count = 1; /* Starts at one. */

  return $count;
}
