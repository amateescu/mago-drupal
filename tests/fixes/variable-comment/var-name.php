<?php

class VarName
{
    /**
     * The label.
     *
     * @var string $label
     */
    public $label = '';

    /** @var int $count */
    public $count = 0;

    /**
     * The weight.
     *
     * @var int $weight The weight, from light to heavy.
     */
    public $weight = 0;

    /**
     * The weights by name.
     *
     * @var array<string, int> $weights
     */
    public $weights = [];

    /**
     * The sort callback.
     *
     * @var callable(int, int): int $sort The comparison.
     */
    public $sort;

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
