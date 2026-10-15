<?php

/**
 * @file
 * Functions with and without the blank line before them.
 */

define('EXAMPLE_LIMIT', 10);
/**
 * After a define() call.
 */
function example_after_define() {
}

$example = 1;
// A comment above the docblock.
/**
 * After a comment.
 */
function example_after_comment() {
}

$example = 2;
// @phpstan-ignore-next-line
/**
 * After a tool comment, which stays with the docblock.
 */
function example_after_tool_comment() {
}

$example = 3;
#[\Deprecated]
function example_attributed() {
}

/**
 * Declares nested functions.
 */
function example_outer() {
  $count = 1;
  /**
   * After a statement in a body.
   */
  function example_nested() {
  }

  if ($count) {
    /**
     * First in a block, which is not reported.
     */
    function example_first_in_block() {
    }
  }
}

$example = 4;
// A comment right above the function, which is not reported.
function example_below_comment() {
}

$example = 5; function example_same_line() {
}
