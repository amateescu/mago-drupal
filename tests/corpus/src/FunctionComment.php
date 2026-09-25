<?php

declare(strict_types=1);

namespace Drupal\corpus;

// @mago-expect lint:drupal/function-comment
function function_comment_missing(string $a): string {
  return $a;
}

// @mago-expect lint:drupal/function-comment
/*
 * Wrong style.
 */
function function_comment_wrong_style(string $a): string {
  return $a;
}

/**
 * A fine function.
 *
 * @param string $a
 *   The description.
 *
 * @return string
 *   The result.
 */
function function_comment_fine(string $a): string {
  return $a;
}

// @mago-expect lint:drupal/function-comment
/**
 * Missing param type.
 *
 * @param $a
 *   The description.
 */
function function_comment_missing_param_type($a): void {
}

// @mago-expect lint:drupal/function-comment
/**
 * Missing param name.
 *
 * @param string
 *   The description.
 */
function function_comment_missing_param_name($a): void {
}

// @mago-expect lint:drupal/function-comment
/**
 * Missing param comment.
 *
 * @param string $a
 */
function function_comment_missing_param_comment($a): void {
}

// @mago-expect lint:drupal/function-comment
/**
 * Param name dot.
 *
 * @param string $a.
 *   The description.
 */
function function_comment_param_name_dot($a): void {
}

// @mago-expect lint:drupal/function-comment
/**
 * Param comment not capital.
 *
 * @param string $a
 *   lowercase description.
 */
function function_comment_param_comment_not_capital($a): void {
}

// @mago-expect lint:drupal/function-comment
/**
 * Param comment full stop.
 *
 * @param string $a
 *   No terminal punctuation
 */
function function_comment_param_comment_full_stop($a): void {
}

// @mago-expect lint:drupal/function-comment
// @mago-expect lint:drupal/function-comment
/**
 * Duplicate return.
 *
 * @return string
 * @return string
 */
function function_comment_duplicate_return(): string {
  return 'x';
}

// @mago-expect lint:drupal/function-comment
/**
 * Missing return comment.
 *
 * @return string
 */
function function_comment_missing_return_comment(): string {
  return 'x';
}

/**
 * $this and static returns are exempt from needing a description.
 */
class FunctionCommentReturnExemptions {

  /**
   * Returns the same instance, typed as static.
   *
   * @return static
   */
  public function chainStatic(): static {
    return $this;
  }

  /**
   * Returns the same instance, typed as $this.
   *
   * @return $this
   */
  public function chainThis(): static {
    return $this;
  }

}

// @mago-expect lint:drupal/function-comment
/**
 * Return var name.
 *
 * @return string $result
 */
function function_comment_return_var_name(): string {
  return 'x';
}

// @mago-expect lint:drupal/function-comment
/**
 * Throws not capital.
 *
 * @throws \Exception
 *   lowercase.
 */
function function_comment_throws_not_capital(): void {
  throw new \Exception('x');
}

// @mago-expect lint:drupal/function-comment
/**
 * Throws no full stop.
 *
 * @throws \Exception
 *   No terminal punctuation
 */
function function_comment_throws_no_full_stop(): void {
  throw new \Exception('x');
}

/**
 * A type-only @throws needs no separate description.
 *
 * @throws \Exception
 */
function function_comment_throws_type_only(): void {
  throw new \Exception('x');
}

// @mago-expect lint:drupal/function-comment
/**
 * Empty sees.
 *
 * @see
 */
function function_comment_empty_sees(): void {
}

// @mago-expect lint:drupal/function-comment
/**
 * See additional text.
 *
 * @see FunctionCommentFixture::method() plus extra text
 */
function function_comment_see_additional_text(): void {
}

// @mago-expect lint:drupal/function-comment
/**
 * See punctuation.
 *
 * @see FunctionCommentFixture::method().
 */
function function_comment_see_punctuation(): void {
}

/**
 * Exercises the constructor exemption and missing @param coverage.
 */
class FunctionCommentFixture {

  public function __construct() {}

  // @mago-expect lint:drupal/function-comment
  /**
   * Missing param coverage.
   *
   * @param string $a
   *   The description.
   */
  public function missingParamCoverage(string $a, string $b): string {
    return $a . $b;
  }

  /**
   * Stands in for the @see target above.
   */
  public function method(): void {
  }

}

/**
 * Documents a parameter with an example at the end of its description.
 *
 * @param array $settings
 *   The settings, keyed by name. For example:
 *   @code
 *   ['enabled' => TRUE, 'labels' => ['first', 'second']]
 *   @endcode
 */
function param_description_ending_in_an_example(array $settings): void {
}

// @mago-expect lint:drupal/function-comment
/**
 * Documents a parameter with prose after the example.
 *
 * @param array $settings
 *   The settings, keyed by name. For example:
 *   @code
 *   ['enabled' => TRUE]
 *   @endcode
 *   The keys are machine names
 */
function prose_after_an_example_still_needs_a_full_stop(array $settings): void {
}

/**
 * Documents a parameter with the example at the star column.
 *
 * @param array $settings
 *   The settings, keyed by name. For example:
 * @code
 * ['enabled' => TRUE, 'labels' => ['first', 'second']]
 * @endcode
 */
function param_description_with_a_star_column_example(array $settings): void {
}
