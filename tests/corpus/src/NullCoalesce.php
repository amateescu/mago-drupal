<?php

declare(strict_types=1);

namespace Drupal\corpus;

/**
 * Holds values for the null coalesce cases.
 */
class NullCoalesceHolder {

  /**
   * A nullable property.
   *
   * @var string|null
   */
  public ?string $p = NULL;

  /**
   * A nullable link to another holder.
   *
   * @var \Drupal\corpus\NullCoalesceHolder|null
   */
  public ?NullCoalesceHolder $next = NULL;

  /**
   * A nullable static property.
   *
   * @var string|null
   */
  public static ?string $cache = NULL;

  /**
   * Returns a nullable value.
   *
   * @return string|null
   *   The value.
   */
  public function get(): ?string {
    return $this->p;
  }

  /**
   * A method that is named like the language construct.
   *
   * @param string|null $value
   *   The value.
   *
   * @return bool
   *   TRUE when there is a value.
   */
  public function isset(?string $value): bool {
    return $value !== NULL;
  }

}

/**
 * Returns a nullable value.
 *
 * @param string|null $value
 *   The value.
 *
 * @return string|null
 *   The value.
 */
function nullable_helper(?string $value): ?string {
  return $value;
}

/**
 * Case isset_plain.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_isset_plain(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect lint:drupal/null-coalesce
  return isset($a) ? $a : 'x';
}

/**
 * Case isset_index.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_isset_index(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect lint:drupal/null-coalesce
  return isset($arr['k']) ? $arr['k'] : '';
}

/**
 * Case isset_quote_style.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_isset_quote_style(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect lint:drupal/null-coalesce
  return isset($arr['k']) ? $arr['k'] : '';
}

/**
 * Case isset_property.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_isset_property(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect lint:drupal/null-coalesce
  return isset($obj->next->p) ? $obj->next->p : NULL;
}

/**
 * Case isset_static_property.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_isset_static_property(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect lint:drupal/null-coalesce
  return isset(NullCoalesceHolder::$cache) ? NullCoalesceHolder::$cache : NULL;
}

/**
 * Case isset_parenthesized_then.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_isset_parenthesized_then(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect lint:drupal/null-coalesce
  return isset($a) ? $a : '';
}

/**
 * Case isset_after_and.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_isset_after_and(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect lint:drupal/null-coalesce
  return $flag and (isset($a) ? $a : '');
}

/**
 * Case isset_after_xor.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_isset_after_xor(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect lint:drupal/null-coalesce
  return $flag xor (isset($a) ? $a : '');
}

/**
 * Case isset_scalar_cast.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_isset_scalar_cast(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect lint:drupal/null-coalesce
  return (string) isset($a) ? $a : '';
}

/**
 * Case isset_call_in_key.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_isset_call_in_key(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect lint:drupal/null-coalesce
  return isset($arr[max(1, 2)]) ? $arr[max(1, 2)] : '';
}

/**
 * Case isset_side_effect_key.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_isset_side_effect_key(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect lint:drupal/null-coalesce
  return isset($arr[$i++]) ? $arr[$i++] : '';
}

/**
 * Case isset_nested.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_isset_nested(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect lint:drupal/null-coalesce(2)
  return isset($a) ? $a : (isset($b) ? $b : '');
}

/**
 * Case isset_in_arguments.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_isset_in_arguments(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect lint:drupal/null-coalesce
  return str_repeat(isset($a) ? $a : '', 2);
}

/**
 * Case null_check_identical.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_null_check_identical(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect lint:drupal/null-coalesce
  return $a === NULL ? '' : $a;
}

/**
 * Case null_check_not_identical.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_null_check_not_identical(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect lint:drupal/null-coalesce
  return $a !== NULL ? $a : '';
}

/**
 * Case null_check_yoda.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_null_check_yoda(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect lint:drupal/null-coalesce
  return NULL === $a ? '' : $a;
}

/**
 * Case null_check_yoda_not_identical.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_null_check_yoda_not_identical(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect lint:drupal/null-coalesce
  return NULL !== $a ? $a : '';
}

/**
 * Case null_check_lower_case.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_null_check_lower_case(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect lint:drupal/null-coalesce
  return $a === NULL ? '' : $a;
}

/**
 * Case null_check_root_namespace.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_null_check_root_namespace(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect lint:drupal/null-coalesce
  return $a === \null ? '' : $a;
}

/**
 * Case null_check_index_spacing.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_null_check_index_spacing(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect lint:drupal/null-coalesce
  return $arr['k'] === NULL ? '' : $arr['k'];
}

/**
 * Case null_check_nullsafe_property.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_null_check_nullsafe_property(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect lint:drupal/null-coalesce
  return $obj->next?->p === NULL ? 'x' : $obj->next?->p;
}

/**
 * Case null_check_static_property.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_null_check_static_property(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect lint:drupal/null-coalesce
  return NullCoalesceHolder::$cache === NULL ? '' : NullCoalesceHolder::$cache;
}

/**
 * Case null_check_parenthesized_operand.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_null_check_parenthesized_operand(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect lint:drupal/null-coalesce
  return $a === NULL ? 'x' : $a;
}

/**
 * Case null_check_method_call.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_null_check_method_call(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect lint:drupal/null-coalesce
  return $obj->get() === NULL ? '' : $obj->get();
}

/**
 * Case null_check_function_call.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_null_check_function_call(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect lint:drupal/null-coalesce
  return nullable_helper($a) === NULL ? 'x' : nullable_helper($a);
}

/**
 * Case null_check_parenthesized_branch.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_null_check_parenthesized_branch(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect lint:drupal/null-coalesce
  return $a === NULL ? ($flag ? 'a' : 'b') : $a;
}

/**
 * Case null_check_loose_branch.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_null_check_loose_branch(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect lint:drupal/null-coalesce
  return $a === NULL ? ($flag ? 'a' : 'b') : $a;
}

/**
 * Case null_check_after_and.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_null_check_after_and(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect lint:drupal/null-coalesce
  return $flag and ($a === NULL ? '' : $a);
}

/**
 * Case null_check_nested.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_null_check_nested(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect lint:drupal/null-coalesce(2)
  return $a === NULL ? ($b === NULL ? '' : $b) : $a;
}

/**
 * Case miss_different_operand.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_miss_different_operand(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  return isset($a) ? $b : '';
}

/**
 * Case miss_negated_isset.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_miss_negated_isset(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  return !isset($a) ? '' : $a;
}

/**
 * Case miss_negated_parens.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_miss_negated_parens(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  return !isset($a) ? '' : $a;
}

/**
 * Case miss_after_and_and.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_miss_after_and_and(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  return $flag && isset($a) ? $a : '';
}

/**
 * Case miss_after_or_or.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_miss_after_or_or(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  return $flag || isset($a) ? $a : '';
}

/**
 * Case miss_multiple_arguments.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_miss_multiple_arguments(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  return isset($a, $b) ? $a : '';
}

/**
 * Case miss_short_ternary.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_miss_short_ternary(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  return isset($a) ?: '';
}

/**
 * Case miss_function_on_then.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_miss_function_on_then(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  return isset($a) ? strtolower($a) : '';
}

/**
 * Case miss_isset_method.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_miss_isset_method(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  return $obj->isset($a) ? $a : '';
}

/**
 * Case miss_isset_array_cast.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_miss_isset_array_cast(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect analysis:redundant-condition
  return (array) isset($a) ? $a : '';
}

/**
 * Case miss_loose_equality.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_miss_loose_equality(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect analysis:possibly-null-operand
  // @mago-expect analysis:null-operand
  return $a == NULL ? '' : $a;
}

/**
 * Case miss_loose_inequality.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_miss_loose_inequality(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect analysis:possibly-null-operand
  // @mago-expect analysis:null-operand
  return $a != NULL ? $a : '';
}

/**
 * Case miss_wrong_branch.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_miss_wrong_branch(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  return $a === NULL ? $a : '';
}

/**
 * Case miss_wrong_branch_not_identical.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_miss_wrong_branch_not_identical(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  return $a !== NULL ? '' : $a;
}

/**
 * Case miss_not_null.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_miss_not_null(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect analysis:redundant-comparison
  // @mago-expect analysis:impossible-condition
  return $a === 0 ? '' : $a;
}

/**
 * Case miss_literal_operand.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_miss_literal_operand(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect analysis:redundant-comparison
  // @mago-expect analysis:impossible-condition
  return 'x' === NULL ? '' : 'x';
}

/**
 * Case miss_negated_operand.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_miss_negated_operand(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect analysis:redundant-comparison
  // @mago-expect analysis:impossible-condition
  return !$a === NULL ? '' : $a;
}

/**
 * Case miss_cast_operand.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_miss_cast_operand(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect analysis:redundant-comparison
  // @mago-expect analysis:impossible-condition
  return (string) $a === NULL ? '' : $a;
}

/**
 * Case miss_sum_operand.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_miss_sum_operand(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect analysis:redundant-comparison
  // @mago-expect analysis:impossible-condition
  return ($i + 1) === NULL ? '' : $i;
}

/**
 * Case miss_different_arguments.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_miss_different_arguments(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  return nullable_helper($a) === NULL ? 'x' : nullable_helper($b);
}

/**
 * Case miss_after_and_and_null.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_miss_after_and_and_null(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  return $flag && $a === NULL ? '' : $a;
}

/**
 * Case miss_after_or_or_null.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_miss_after_or_or_null(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  return $flag || $a !== NULL ? $a : '';
}

/**
 * Case miss_else_coalesce.
 *
 * @param \Drupal\corpus\NullCoalesceHolder $obj
 *   The holder.
 * @param string|null $a
 *   The first value.
 * @param string|null $b
 *   The second value.
 * @param bool $flag
 *   A flag.
 * @param array<array-key, string|null> $arr
 *   The array.
 * @param int $i
 *   A counter.
 *
 * @return mixed
 *   The result.
 */
function coalesce_miss_else_coalesce(
  NullCoalesceHolder $obj,
  ?string $a = NULL,
  ?string $b = NULL,
  bool $flag = FALSE,
  array $arr = [],
  int $i = 0,
): mixed {
  // @mago-expect analysis:redundant-null-coalesce
  return $a === NULL ? '' : $a ?? 'z';
}
