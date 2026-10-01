<?php

/**
 * Has a period after the parameter name.
 *
 * @param string $a.
 *   The a.
 * @param string $b. The b.
 * @param string ...$c...
 *   The c, left alone.
 */
function param_dot($a, $b, ...$c): void
{
}

/**
 * Leaves a parameter description without a full stop.
 *
 * @param string $plain
 *   The plain text
 * @param string $url
 *   See https://www.drupal.org/node/1
 * @param string $colon
 *   One of:
 * @param string $quoted
 *   The value "x."
 * @param string $two_lines
 *   The first line and
 *   the second line
 */
function param_full_stop($plain, $url, $colon, $quoted, $two_lines): void
{
}

/**
 * Names the return value.
 *
 * @return string $label
 *   The label.
 */
function return_name(): string
{
    return '';
}

/**
 * Names the return value with no description, which keeps the name.
 *
 * @return string $label
 */
function return_name_alone(): string
{
    return '';
}

/**
 * Ends the references with punctuation.
 *
 * @see other_function().
 * @see \Drupal\Core\Url::fromRoute()...
 * @see https://www.drupal.org/node/1 for the details.
 * @see some_function()
 *   Its description ends in a period.
 */
function see_punctuation(): void
{
}
