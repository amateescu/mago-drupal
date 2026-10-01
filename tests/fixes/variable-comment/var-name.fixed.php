<?php

class VarName
{
    /**
     * The label.
     *
     * @var string
     */
    public $label = '';

    /** @var int */
    public $count = 0;

    /**
     * The weight.
     *
     * @var int The weight, from light to heavy.
     */
    public $weight = 0;

    /**
     * Names another property.
     *
     * @var int $other
     */
    public $named = 0;

    /**
     * Declares two properties at once.
     *
     * @var int $first
     */
    public $first = 0, $second = 'x';

    /**
     * Names the property with brackets after it.
     *
     * @var string $items[]
     */
    public $items = [];
}
