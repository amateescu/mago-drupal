<?php

class TypeNames
{
    /**
     * The id.
     *
     * @var integer|string
     */
    public $id = 0;

    /** @var boolean */
    public $flag = false;

    /**
     * The state.
     *
     * @var Boolean The state, on or off.
     */
    public $state = false;

    /**
     * The rows.
     *
     * @var NULL|Array
     */
    public $rows = null;

    /**
     * The count, with its name after the type.
     *
     * @var integer $count
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
