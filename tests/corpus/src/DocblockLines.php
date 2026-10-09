<?php

declare(strict_types=1);

namespace Drupal\corpus;

// @mago-expect lint:drupal/doc-comment
/**
 * Has a tag with no space after its star.
 *
 *@return int
 *   The number.
 */
function doc_star_tag_without_space(): int {
  return 1;
}

// @mago-expect lint:drupal/doc-comment
/**
 * Has a tag with two spaces after its star.
 *
 *  @return int
 *   The number.
 */
function doc_star_tag_with_two_spaces(): int {
  return 1;
}

// @mago-expect lint:drupal/doc-comment
/**
 * Has a tag with a tab after its star.
 *
 * @param int $value
 *   The value.
 *
 *	@return int
 *   The number.
 */
function doc_star_tag_with_a_tab(int $value): int {
  return $value;
}

// @mago-expect lint:drupal/doc-comment
/**
 * Has a long description line with no space after its star.
 *
 *Second paragraph.
 */
function doc_star_long_description_without_space(): void {
}

/**
 * Has a long description indented after its star.
 *
 *  An indented line that is not before a tag is fine.
 */
function doc_star_indented_text_is_fine(): void {
}

// Coder checks the stars of a docblock only before a declaration keyword.
/**
 * Is before a final class.
 *
 *No space here, and not reported.
 */
final class DocStarFinalClass {}

// @mago-expect lint:drupal/doc-comment(2)
/** Ends with two stars. **/
function doc_end_one_line_two_stars(): void {
}

// @mago-expect lint:drupal/doc-comment
/**
 * Ends with three stars.
 ***/
function doc_end_three_stars(): void {
}

// @mago-expect lint:drupal/doc-comment
/**
 * Ends with the closer on its own line.
 **/
function doc_end_closer_on_its_own_line(): void {
}

// @mago-expect lint:drupal/doc-comment
/**
 * Has a parameter docblock with a bad end.
 *
 * @param int $value
 *   The value.
 * @param int $other
 *   The other value.
 */
function doc_end_parameter_docblock(
  int $value,
  /**
   * The other value.
   **/
  int $other = 0,
): int {
  return $value + $other;
}

/**
 * Has a docblock with a bad end in its body.
 */
function doc_end_body_docblock_is_not_checked(): void {
  // @mago-expect lint:drupal/inline-comment
  /** Ends with two stars. **/
  $value = 1;
  echo $value;
}

// @mago-expect lint:drupal/doc-comment
/**
 * Groups the parameters twice.
 *
 * @param string $first
 *   The first parameter.
 *
 * @param string $second
 *   The second parameter.
 */
function doc_param_group_split_by_a_blank_line(string $first, string $second): string {
  return $first . $second;
}

// @mago-expect lint:drupal/doc-comment(2)
/**
 * Groups the parameters three times.
 *
 * @param string $first
 *   The first parameter.
 *
 * @param string $second
 *   The second parameter.
 *
 * @param string $third
 *   The third parameter.
 */
function doc_param_group_three_groups(string $first, string $second, string $third): string {
  return $first . $second . $third;
}

// @mago-expect lint:drupal/doc-comment
/**
 * Follows an example with the parameters.
 *
 * @code
 * $example = 1;
 * @endcode
 * @param string $value
 *   The value.
 */
function doc_param_group_after_example_with_no_blank_line(string $value): string {
  return $value;
}

// @mago-expect lint:drupal/doc-comment
/**
 * Follows a note with the parameters.
 *
 * @todo Document this better.
 * @param string $value
 *   The value.
 */
function doc_param_group_after_todo_with_no_blank_line(string $value): string {
  return $value;
}

/**
 * Keeps the parameters in one group.
 *
 * @param string $first
 *   The first parameter.
 * @param string $second
 *   The second parameter.
 *
 * @return string
 *   The text.
 */
function doc_param_group_one_group_is_fine(string $first, string $second): string {
  return $first . $second;
}

// @mago-expect lint:drupal/doc-comment
/**
 * Ends with two dots..
 */
function doc_dots_summary(): void {
}

// @mago-expect lint:drupal/doc-comment(2)
/**
 * Ends with two dots..
 *
 * The long description ends with two dots too, after a space ..
 */
function doc_dots_summary_and_long_description(): void {
}

/**
 * Ends with an ellipsis...
 *
 * Four dots are fine too....
 */
function doc_dots_three_or_more_are_fine(): void {
}

/**
 * Has dots in the middle of a line.. Not at the end.
 *
 * @return int
 *   Text after a tag is not read..
 */
function doc_dots_after_a_tag_are_fine(): int {
  return 1;
}
