<?php

/**
 * Has tag groups to fix.
 */
class SpacingFix
{
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
}
