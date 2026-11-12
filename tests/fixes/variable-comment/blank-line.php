<?php

class BlankLine
{
    /**
     * The label.
     *
     * @var string
     */

    public $label = '';

    /**
     * The count, with an attribute below the blank line.
     */

    #[Deprecated]
    public int $count = 0;

    /**
     * The weight, right above its property.
     */
    public int $weight = 0;
}
