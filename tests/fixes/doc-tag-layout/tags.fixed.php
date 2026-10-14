<?php

/**
 * Has a period after the parameter name.
 *
 * @param string $a
 *   The a.
 * @param string $b
 *   The b.
 * @param string ...$c...
 *   The c, left alone.
 */
function param_dot($a, $b, ...$c): void
{
}

/**
 * Names the return value.
 *
 * @return string
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
