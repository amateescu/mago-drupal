<?php

declare(strict_types=1);

namespace Drupal\corpus;

use function preg_match;
use function preg_replace;

function evil_flag(string $input): ?string
{
    // @mago-expect lint:drupal/preg-security
    return preg_replace('/(.*)/e', 'strtoupper("$1")', $input);
}

function evil_flag_with_other_modifiers(string $input): ?string
{
    // @mago-expect lint:drupal/preg-security
    return preg_replace('#(.*)#ie', 'strtoupper("$1")', $input);
}

function evil_flag_with_bracket_delimiters(string $input): ?string
{
    // @mago-expect lint:drupal/preg-security
    return preg_replace('{(.*)}e', 'strtoupper("$1")', $input);
}

// preg_grep is deliberately not imported. Real Drupal code rarely imports
// functions, and the unqualified call must still match.
function evil_flag_without_a_function_import(string $input): array|false
{
    // @mago-expect lint:drupal/preg-security
    return preg_grep('/(.*)/e', [$input]);
}

function safe_pattern(string $input): int|false
{
    return preg_match('/^[a-z]+$/i', $input);
}

function letter_e_in_the_pattern(string $input): int|false
{
    return preg_match('/^[e]+$/', $input);
}
