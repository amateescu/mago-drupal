<?php

/**
 * Has a blank line below it.
 */

function blank_line_below(): void
{
}

/**
 * Has two spaces after a type, and a description on the tag line.
 *
 * @param string  $a
 *   The a.
 * @param int $b The b.
 * @param string$c
 *   The c.
 */
function param_line($a, $b, $c): void
{
}

/**
 * Indents its descriptions wrong.
 *
 * @param int $a
 *  The a, one space short.
 *     A deeper second line stays.
 * @param int $b
 *     The b, two spaces too deep.
 *
 * @return int
 *  The value.
 *
 * @throws \Exception
 *  When it fails.
 */
function indents($a, $b)
{
    return $a + $b;
}

/**
 * Has an example at the star column inside a description.
 *
 * @param array $settings
 *   The settings. For example:
 * @code
 * ['enabled' => TRUE]
 * @endcode
 */
function star_column_example(array $settings): void
{
}

/**
 * Leaves a description with two @return tags alone.
 *
 * @return int
 *  The value.
 * @return string
 *   The other value.
 */
function two_returns()
{
    return 1;
}

/**
 * @file
 * Leaves a file docblock that sits on a function alone.
 */

function after_file_docblock(): void
{
}

class SpacingMethods
{
    /**
     * Has a blank line above its attribute.
     */

    #[\Deprecated]
    public function attributed(): void
    {
    }

    /**
     * Leaves a constructor alone, as the rule does.
     *
     * @param int  $a
     *  The a.
     */
    public function __construct($a)
    {
    }
}
