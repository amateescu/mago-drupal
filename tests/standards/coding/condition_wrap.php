// Before.
if ($first_condition_with_a_long_name && $second_condition_with_a_long_name || $third_condition_with_a_long_name && $fourth) {
  one();
}
if ($first_condition
  && $second_condition
) {
  two();
}
if (
  $first_condition
  && $second_condition
) {
  three();
}

// After.
if (
  $first_condition_with_a_long_name
  && $second_condition_with_a_long_name
  || $third_condition_with_a_long_name
  && $fourth
) {
  one();
}
if ($first_condition && $second_condition) {
  two();
}
if (
  $first_condition
  && $second_condition
) {
  three();
}
