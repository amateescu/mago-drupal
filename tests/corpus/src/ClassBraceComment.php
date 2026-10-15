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

/**
 * A comment after the closing brace of a class.
 */
class BraceClosingComment {

  /**
   * The count.
   */
  public int $count = 0;

  // @mago-expect lint:drupal/post-statement-comment

} // End of the class.

/**
 * A block comment after the closing brace of an interface.
 */
interface BraceClosingBlockInterface {
  // @mago-expect lint:drupal/post-statement-comment
} /* End of the interface. */

/**
 * A docblock after the closing brace of a trait.
 */
trait BraceClosingDocblockTrait {
  // @mago-expect lint:drupal/doc-comment
  // @mago-expect lint:drupal/post-statement-comment
} /** End of the trait. */

/**
 * A directive after the closing brace of an enum, where it applies to nothing.
 */
enum BraceClosingEnum {

  case One;

  // @mago-expect lint:drupal/post-statement-comment

} // phpcs:ignore Drupal.Classes.ClassDeclaration

/**
 * Comments after other closing braces, which are not reported.
 */
function brace_closing_other(bool $flag): int {
  if ($flag) {
    return \spl_object_id(new class {} /* The closing brace of an anonymous class. */);
  } // End of the condition.

  return 0;
} // End of the function.
