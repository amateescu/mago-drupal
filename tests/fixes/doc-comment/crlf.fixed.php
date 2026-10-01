<?php

/**
 * @file
 * Holds the CRLF cases.
 */

/**
 * Has its text on the opening line.
 */
class CrlfSpacing
{
    /**
     * @var int
     */
    public $oneLine;

    /**
     * Has no blank line before the tags.
     *
     * @var int
     */
    public $noBlankBeforeTags;

    /**
     * Joins its sections.
     *
     * @param int $a
     *   The a.
     *
     * @return int
     *   The value.
     *
     * @see other()
     */
    public function joinedSections($a)
    {
        return $a;
    }
}
