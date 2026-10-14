<?php

/**
 * @file
 * Holds the CRLF cases.
 */

/**
 * Has tag groups to fix.
 */
class CrlfSpacing
{
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
