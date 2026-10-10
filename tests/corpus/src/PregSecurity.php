<?php

declare(strict_types=1);

namespace Drupal\corpus;

use function preg_match;
use function preg_replace;

// @mago-expect lint:drupal/function-comment
function evil_flag(string $input): ?string {
  // @mago-expect lint:drupal/preg-security
  return preg_replace('/(.*)/e', 'strtoupper("$1")', $input);
}

// @mago-expect lint:drupal/function-comment
function evil_flag_with_other_modifiers(string $input): ?string {
  // @mago-expect lint:drupal/preg-security
  return preg_replace('#(.*)#ie', 'strtoupper("$1")', $input);
}

// @mago-expect lint:drupal/function-comment
function evil_flag_with_bracket_delimiters(string $input): ?string {
  // @mago-expect lint:drupal/preg-security
  return preg_replace('{(.*)}e', 'strtoupper("$1")', $input);
}

// preg_grep is deliberately not imported.
// Real Drupal code rarely imports functions, and the unqualified call must
// still match.
// @mago-expect lint:drupal/function-comment
function evil_flag_without_a_function_import(string $input): array|false {
  // @mago-expect lint:drupal/preg-security
  return preg_grep('/(.*)/e', [$input]);
}

// The concatenation adds modifiers after the e that the literal ends with.
// @mago-expect lint:drupal/function-comment
function evil_flag_before_a_concatenation(string $input, string $modifiers): ?string {
  // @mago-expect lint:drupal/preg-security
  return preg_replace('/(.*)/e' . $modifiers, 'strtoupper("$1")', $input);
}

// @mago-expect lint:drupal/function-comment
function safe_pattern(string $input): int|false {
  return preg_match('/^[a-z]+$/i', $input);
}

// @mago-expect lint:drupal/function-comment
function letter_e_in_the_pattern(string $input): int|false {
  return preg_match('/^[e]+$/', $input);
}

// The e follows the opening delimiter, so it is part of the pattern.
// @mago-expect lint:drupal/function-comment
function letter_e_after_the_opening_delimiter(string $input, string $type): int|false {
  return preg_match('/entity_' . $type . '/', $input);
}

// A backslash escapes the delimiter before the e, so the pattern goes on.
// @mago-expect lint:drupal/function-comment
function letter_e_after_an_escaped_delimiter(string $input, string $id): int|false {
  return preg_match('/node\/edit' . $id . '/', $input);
}
