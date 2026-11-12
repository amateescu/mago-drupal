// Before.
$short = example_function_with_a_long_name($first_argument, $second_argument, $third);
$joined = $first_part_of_the_value
  + $second_part_of_the_value;
$text = 'The first part of the text '
  . 'and the second part.';
$long = example_function_with_a_long_name($first_argument, $second_argument, $third_argument, $fourth_argument, $fifth);

// After.
$short = example_function_with_a_long_name($first_argument, $second_argument, $third);
$joined = $first_part_of_the_value + $second_part_of_the_value;
$text = 'The first part of the text ' . 'and the second part.';
$long = example_function_with_a_long_name($first_argument, $second_argument, $third_argument, $fourth_argument, $fifth);
