<?php

/**
 * Holds the stars.
 *
 *@var int
 */
class StarSpacingFix
{
    /**
     * Has a tag with two spaces after its star.
     *
     * @param int $value
     *   The value.
     *
     *  @return int
     *   The number.
     */
    public function twoSpaces(int $value): int
    {
        return $value;
    }

    /**
     * Has a tab and a long description with no space after the star.
     *
     *Second paragraph.
     *
     *	@return int
     *   The number.
     */
    public function tabAndNoSpace(): int
    {
        return 1;
    }

    /**
     * Keeps an indented description line.
     *
     *   An indented line is fine.
     *
     * @see https://example.com/
     */
    public function indentedText(): void
    {
    }

    public function inBody(): void
    {
        /**
         * Describes a static variable.
         *
         *@var int
         */
        static $count = 0;

        /**
         * Describes a plain variable.
         *
         *@var int
         */
        $other = 1;
        echo $count . $other;
    }
}

/**
 * Is before a final class.
 *
 *No space here, and left alone.
 */
final class StarSpacingFinalClass
{
}
