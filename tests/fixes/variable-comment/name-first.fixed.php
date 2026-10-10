<?php

class NameFirst
{
    /** @var $count int */
    public $count = 0;

    /**
     * The weight.
     *
     * @var $weight int The weight, from light to heavy.
     */
    public $weight = 0;

    /**
     * The weights by name.
     *
     * @var $map array<string, int>
     */
    public $map = [];

    /**
     * The amount.
     *
     * @var $amount int
     *   Counted in cents.
     */
    public $amount = 0;

    /**
     * Names another property.
     *
     * @var $other int
     */
    public $named = 0;

    /**
     * Declares two properties at once.
     *
     * @var $first int
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
