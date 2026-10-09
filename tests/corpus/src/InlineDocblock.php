<?php

declare(strict_types=1);

namespace Drupal\corpus;

use function strtoupper;

// @mago-expect lint:drupal/function-comment
function inline_docblock_with_text(): int {
  // @mago-expect lint:drupal/inline-comment
  /** Counts the rows. */
  $count = 1;
  return $count;
}

// @mago-expect lint:drupal/function-comment
function inline_docblock_with_text_before_a_tag(): int {
  // @mago-expect lint:drupal/inline-comment
  /**
   * The count.
   *
   * @var int
   */
  $count = 1;
  return $count;
}

// @mago-expect lint:drupal/function-comment
function inline_docblock_tag_in_text(): int {
  // @mago-expect lint:drupal/inline-comment
  /** The count. @var int */
  $count = 1;
  return $count;
}

// @mago-expect lint:drupal/function-comment
function inline_docblock_star_after_opener(): int {
  // @mago-expect lint:drupal/inline-comment
  /** * @var int */
  $count = 1;
  return $count;
}

// @mago-expect lint:drupal/function-comment
function inline_docblock_in_a_branch(bool $flag): string {
  if ($flag) {
    // @mago-expect lint:drupal/inline-comment
    /** Shouts. */
    return strtoupper('a');
  }

  return '';
}

// @mago-expect lint:drupal/function-comment
function inline_docblock_before_a_closing_brace(bool $flag): string {
  if ($flag) {
    return 'a';

    // @mago-expect lint:drupal/inline-comment
    /** Nothing follows. */
  }

  return '';
}

// @mago-expect lint:drupal/function-comment
function inline_docblock_with_a_tag_first(): int {
  /** @var int $count */
  $count = 1;
  return $count;
}

// @mago-expect lint:drupal/function-comment
function inline_docblock_before_a_static_variable(): int {
  /** Remembers the count. */
  static $count = 0;
  return ++$count;
}

// @mago-expect lint:drupal/function-comment
function inline_docblock_before_a_closure(): callable {
  $callback = /** Builds the value. */ function (): int {
    return 1;
  };

  return $callback;
}

// @mago-expect lint:drupal/function-comment
function inline_docblock_before_a_nested_function(): int {
  /** Builds the value. */
  function inline_docblock_nested(): int {
    return 1;
  }

  return inline_docblock_nested();
}

// @mago-expect lint:drupal/function-comment
function inline_docblock_with_three_stars(): int {
  // @mago-format-ignore-start
  /*** Not a docblock. */
  $count = 1;
  // @mago-format-ignore-end
  return $count;
}

// @mago-expect lint:drupal/function-comment
function inline_docblock_before_a_class(): int {
  // A comment between the docblock and the class is skipped.
  /** Holds the value. */
  // Another comment.
  // @mago-expect lint:drupal/class-comment
  class InlineDocblockLocal {}

  return 1;
}

/**
 * Documents the file level code, which no body holds.
 */
const INLINE_DOCBLOCK_LIMIT = 5;

// @mago-expect lint:drupal/class-comment
class InlineDocblockHolder {

  use InlineDocblockTrait;

  // @mago-expect lint:drupal/inline-comment
  // @mago-expect lint:drupal/doc-comment
  /** Closes the class. */

}

// @mago-expect lint:drupal/class-comment
trait InlineDocblockTrait {}

// @mago-expect lint:drupal/class-comment
enum InlineDocblockSuit {

  /**
   * The first suit, documented as a declaration.
   */
  case Hearts;

}

// @mago-expect lint:drupal/class-comment
final class InlineDocblockReadonly {

  // @mago-expect lint:drupal/property-visibility
  /**
   * A readonly property with no visibility keyword, documented as one.
   */
  readonly int $count;

}
