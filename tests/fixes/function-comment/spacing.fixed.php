<?php

/**
 * Has a blank line below it.
 */
function blank_line_below(): void
{
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

    #[\Deprecated]
    /**
     * Has a blank line below it, and an attribute above it.
     */
    public function belowAttribute(): void
    {
    }
}
