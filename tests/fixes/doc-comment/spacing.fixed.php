<?php

/**
 * Has its text on the opening line.
 */
class SpacingFix
{
    /**
     * @var int
     */
    public $oneLine;

    /**
     * Ends with a blank line.
     */
    public $blankAtEnd;

    /**
     * Starts with a blank line.
     */
    public $blankAtStart;

    /**
     * Has two spaces before the short description.
     */
    public $twoSpaces;

    /**
     * Has no space before the short description.
     */
    public $noSpace;

    /**
     * Has two blank lines below.
     *
     * Before the long description.
     */
    public $twoBlanksBetween;

    /**
     * Has wide tag values.
     *
     * @see other()
     * @todo Something.
     */
    public $wideValues;

    /**
     * @var  int
     */
    public $noShortDescription;

    /**
     * Leaves a docblock in a body alone.
     */
    public function body(): void
    {
        /** @var int $x */
        $x = 1;
    }
}

/**
 * @defgroup  spacing_fix Spacing fix
 * @{
 */
