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

// Coder wants an upper-case letter first, and a `$` is not one.
// @mago-expect lint:drupal/doc-comment
/**
 * $this, static and void returns are exempt from needing a description.
 */
class FunctionCommentReturnExemptions {

  /**
   * Returns nothing, which Coder 9 lets go without a description.
   *
   * @return void
   */
  public function nothing(): void {
  }

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

// @mago-expect lint:drupal/doc-comment
// @mago-expect lint:drupal/function-comment
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

// @mago-expect lint:drupal/function-comment
/**
 * Param type with a space.
 *
 * @param int string $a
 *   The description.
 */
function function_comment_param_type_spaces($a): void {
}

// @mago-expect lint:drupal/function-comment
/**
 * Param type with a space, on a variadic parameter.
 *
 * @param int string ...$a
 *   The description.
 */
function function_comment_param_type_spaces_variadic(...$a): void {
}

/**
 * Param types with brackets keep their spaces.
 *
 * @param array<int, string> $a
 *   The first.
 * @param array{id: int, name: string} $b
 *   The second.
 * @param callable(int, int): int $c
 *   The third.
 * @param int|null $d
 *   The fourth.
 */
function function_comment_param_type_brackets($a, $b, $c, $d): void {
}

// @mago-expect lint:drupal/function-comment
/**
 * Return type with a space.
 *
 * @return int string
 *   The description.
 */
function function_comment_return_type_spaces(): int {
  return 1;
}

/**
 * Return type with a space and no description below is not this report.
 *
 * @return int string
 */
function function_comment_return_type_spaces_no_description(): int {
  return 1;
}

/**
 * Return types with brackets keep their spaces.
 *
 * @return array<int, string>
 *   The first.
 */
function function_comment_return_type_brackets(): array {
  return [];
}

/**
 * Return type that is a callable with a variable inside it.
 *
 * @return callable(int $a): int
 *   The callable.
 */
function function_comment_return_type_callable(): callable {
  return static fn (int $a): int => $a;
}

// @mago-expect lint:drupal/function-comment
/**
 * Return type missing, with the description below.
 *
 * @return
 *   Mixed result.
 */
function function_comment_return_type_missing() {
  return 1;
}

// @mago-expect lint:drupal/function-comment
/**
 * Return type missing, with a type on the line below.
 *
 * @return
 *   int The result.
 */
function function_comment_return_type_missing_type_below(): int {
  return 1;
}

/**
 * Return type that is a literal zero.
 *
 * @return 0
 *   Always zero.
 */
function function_comment_return_type_zero(): int {
  return 0;
}

// @mago-expect lint:drupal/function-comment
/**
 * Throws with the description on the tag's line.
 *
 * @throws \Exception Failed to load.
 */
function function_comment_throws_same_line(): void {
  throw new \Exception('x');
}

// @mago-expect lint:drupal/function-comment
/**
 * Throws with the description on the tag's line and a blank line below.
 *
 * @throws \Exception Failed to load.
 *
 * @return int
 *   The count.
 */
function function_comment_throws_same_line_then_blank(): int {
  throw new \Exception('x');
}

/**
 * Throws types with no description at all.
 *
 * @throws \LogicException|\RuntimeException
 * @throws \Foo2Bar
 */
function function_comment_throws_types_only(): void {
  throw new \Exception('x');
}

/**
 * Throws with a description below the tag.
 *
 * @throws \Exception
 *   Failed to load.
 */
function function_comment_throws_next_line(): void {
  throw new \Exception('x');
}

// @mago-expect lint:drupal/function-comment
/**
 * A file docblock above a function documents the file, not the function.
 *
 * @param int string $a
 *   The description.
 *
 * @file
 */
function function_comment_file_docblock($a): void {
}

/**
 * Checks the constructors that have a docblock.
 */
class FunctionCommentConstructors {

  // @mago-expect lint:drupal/function-comment
  /**
   * Constructs the object.
   *
   * @param int $a
   *   The first.
   */
  public function __construct(int $a, int $b) {}

}

/**
 * Has constructors with no docblock, in any case.
 */
class FunctionCommentConstructorsBare {

  public function __CONSTRUCT() {}

}

/**
 * Has a constructor with a spaced param type.
 */
class FunctionCommentConstructorTypes {

  // @mago-expect lint:drupal/function-comment
  /**
   * Constructs the object.
   *
   * @param int string $a
   *   The first.
   */
  public function __construct($a) {}

}

/**
 * Has a constructor with a wrong comment style.
 */
class FunctionCommentConstructorStyle {

  // @mago-expect lint:drupal/function-comment
  // Constructs the object.
  public function __construct() {}

}
