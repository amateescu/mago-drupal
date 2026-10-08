<?php

class TypeNames
{
    /**
     * The id.
     *
     * @var int|string
     */
    public $id = 0;

    /** @var bool */
    public $flag = false;

    /**
     * The state.
     *
     * @var bool The state, on or off.
     */
    public $state = false;

    /**
     * The rows.
     *
     * @var null|array
     */
    public $rows = null;

    /**
     * The count, with its name after the type.
     *
     * @var int
     */
    public $count = 0;

    /**
     * Coder does not look inside a generic.
     *
     * @var array<integer>
     */
    public $ids = [];

    /**
     * A class with the same name.
     *
     * @var \Integer
     */
    public $wrapped;
}
