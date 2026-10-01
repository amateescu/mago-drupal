<?php

/** Has its text on the opening line.
 */
class SpacingFix
{
    /** @var int */
    public $oneLine;

    /**
     * Ends with a blank line.
     *
     */
    public $blankAtEnd;

    /**
     *
     * Starts with a blank line.
     */
    public $blankAtStart;

    /**
     *  Has two spaces before the short description.
     */
    public $twoSpaces;

    /**
     *Has no space before the short description.
     */
    public $noSpace;

    /**
     * Has two blank lines below.
     *
     *
     * Before the long description.
     */
    public $twoBlanksBetween;

    /**
     * Has no blank line before the tags.
     * @var int
     */
    public $noBlankBeforeTags;

    /**
     * Has two blank lines before the tags.
     *
     *
     * @var int
     */
    public $twoBlanksBeforeTags;

    /**
     * Joins its sections.
     *
     * @param int $a
     *   The a.
     * @return int
     *   The value.
     * @throws \Exception
     *   When it fails.
     */
    public function joinedSections($a)
    {
        return $a;
    }

    /**
     * Has two blank lines after a section.
     *
     * @param int $a
     *   The a.
     *
     *
     * @see other()
     */
    public function twoBlanksAfterSection($a)
    {
    }

    /**
     * Has wide tag values.
     *
     * @see  other()
     * @todo   Something.
     */
    public $wideValues;

    /**
     * Leaves an example right after the description.
     * @code
     * other();
     * @endcode
     */
    public $exampleFirst;

    /**
     * Leaves a phpcs line before the tags.
     * phpcs:ignore Drupal.Commenting.DocComment.SpacingBeforeTags
     * @var int
     */
    public $directiveBeforeTags;

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
 *
 */
