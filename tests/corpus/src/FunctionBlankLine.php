<?php

/**
 * @file
 * Exercises the rule about the blank line before a function.
 */

declare(strict_types=1);

$function_blank_line_count = 1;
// @mago-expect lint:drupal/function-blank-line
/**
 * Comes right after a statement.
 */
function function_blank_line_after_statement(): void {
}

if (getenv('CORPUS_FUNCTION_BLANK_LINE') !== FALSE) {
  $function_blank_line_count++;
}
// @mago-expect lint:drupal/function-blank-line
/**
 * Comes right after the closing brace of an if.
 */
function function_blank_line_after_if(): void {
}

/**
 * Declares a function after a statement in its body.
 */
function function_blank_line_outer(int $count): void {
  $count++;
  // @mago-expect lint:drupal/function-blank-line
  /**
   * Comes right after a statement in a body.
   */
  function function_blank_line_nested(): void {
  }

  if ($count > 1) {
    /**
     * Comes first in a block, which is not reported.
     */
    function function_blank_line_first_in_block(): void {
    }
  }
}

$function_blank_line_count = 2;
// @mago-expect lint:drupal/missing-function-comment
function function_blank_line_below_comment(): void {
}

$function_blank_line_count = 3;

/**
 * Has the blank line.
 */
function function_blank_line_fine(): void {
}
