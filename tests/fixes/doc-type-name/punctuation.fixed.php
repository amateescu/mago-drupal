<?php

class TypePunctuation
{
    /**
     * The label.
     *
     * @var string
     */
    public $label = '';

    /**
     * The count, an old name with a full stop.
     *
     * @var int
     */
    public $count = 0;

    /**
     * The ids.
     *
     * @var int|string
     */
    public $ids = 0;

    /**
     * Coder keeps a comma.
     *
     * @var int,
     */
    public $comma = 0;

    /**
     * Checks the values.
     *
     * @param int $a
     *   The first.
     * @param string $b
     *   The second.
     * @param bool $c
     *   The third.
     * @param string ...$rest
     *   The rest.
     *
     * @return bool
     *   The result.
     */
    public function check($a, $b, $c, ...$rest)
    {
        return $a && $b && $c && $rest;
    }
}
