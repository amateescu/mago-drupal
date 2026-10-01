<?php

declare(strict_types=1);

namespace Drupal\corpus;

/**
 * Holds the comment whitespace that Coder accepts, so none of it reports.
 */
final class CommentSpacing
{
    /**
     * Starts its tags with an example.
     * @code
     * $value = 1;
     * @endcode
     */
    public int $example = 0;

    /**
     * Has a phpcs line right above its tags.
     * phpcs:ignore Drupal.Commenting.VariableComment.Missing
     * @var int
     */
    public $directive = 0;

    /**
     * Constructs the object, which the rule leaves alone.
     *
     * @param int  $value
     *  The value.
     */
    public function __construct(int $value)
    {
        $this->example = $value;
    }

    /**
     * Continues list items and a to-do in comment lines.
     */
    public function listItems(): void
    {
        // The steps:
        // - The first step, which
        //   wraps under its dash.
        // 1. A numbered step, which
        //    wraps under its text.
        // @todo Do this, which
        //   wraps under the tag.
    }

    /**
     * Skips the comment lines Coder skips.
     */
    public function skippedComments(bool $flag): bool
    {
        if ($flag) {
            $flag = false;
        } //Ends the if.
        // An example:
        // @code
        //   $flag = true;
        //$flag = false;
        // @endcode
        //phpcs:ignore Drupal.Commenting.InlineComment.NoSpaceBefore
        return $flag;
    }
}
