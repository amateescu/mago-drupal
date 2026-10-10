<?php

class InlineVariableDeclaration
{
    /** @var $weight int */
    protected $weight;

    /** @var $limit int */
    const LIMIT = 10;

    /**
     * @var $rows array
     */
    // Explains the property.
    private static $rows;

    public function read(array $rows): int
    {
        /** @var $count int */
        static $count;
        $count = $rows[0];

        return $count + $this->weight;
    }
}
