// Before.
function example_long_function_name(
  array $first_parameter_name,
  string $second_parameter_name,
  int $third_parameter_name = 0,
  bool $fourth_parameter_name = FALSE
): void {
}

// After.
function example_long_function_name(
  array $first_parameter_name,
  string $second_parameter_name,
  int $third_parameter_name = 0,
  bool $fourth_parameter_name = FALSE,
): void {
}
