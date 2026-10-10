<?php

class NameFirst
{
    /** @var int */
    public $count = 0;

    /**
     * The weight.
     *
     * @var int The weight, from light to heavy.
     */
    public $weight = 0;

    /**
     * The weights by name.
     *
     * @var array<string, int>
     */
    public $map = [];

    /**
     * The amount.
     *
     * @var int
     *   Counted in cents.
     */
    public $amount = 0;

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
     * Has no type after the name.
     *
     * @var $bare
     */
    public $bare;

    /**
     * Has a type cut short.
     *
     * @var $broken array<string,
     */
    public $broken = [];

    /**
     * Has the type on the next line.
     *
     * @var
     *   $below int
     */
    public $below = 0;

    /**
     * Points back at the object.
     *
     * @var $this|null
     */
    public $parent;
}
