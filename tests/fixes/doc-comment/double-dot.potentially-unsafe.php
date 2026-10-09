<?php

/**
 * Ends with two dots..
 *
 * The long description ends with two dots too, after a space ..
 *
 * @return int
 *   Text after a tag keeps its dots..
 */
function double_dot_both(): int
{
    return 1;
}

/**
 * Ends with three dots...
 */
function double_dot_three(): void
{
}

function double_dot_in_body(): void
{
    /**
     * Ends with two dots..
     */
    $value = 1;
    echo $value;
}
