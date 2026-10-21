<?php

declare(strict_types=1);

namespace Drupal\corpus;

/**
 * Holds the comment whitespace that Coder accepts, so none of it reports.
 */
final class CommentSpacing {

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
   * Constructs the object.
   *
   * @param int $value
   *   The value.
   */
  public function __construct(int $value) {
    $this->example = $value;
  }

  /**
   * Continues list items and a to-do in comment lines.
   */
  public function listItems(): void {
    // The steps:
    // - The first step, which
    //   wraps under its dash.
    // 1. A numbered step, which
    //    wraps under its text.
    // @todo Do this, which
    //   wraps under the tag.
  }

  /**
   * Draws a tree in comment lines.
   */
  public function tree(): void {
    // Coder joins the lines into `root-`, which isn't a plain word.
    // @mago-expect lint:drupal/inline-comment-punctuation(1)
    // root
    // - child
  }

  /**
   * Holds one-word first lines that run into the next line.
   *
   * Coder joins the lines of a comment with nothing between them. The first
   * comment starts with `rootChild`, which isn't all lower case, and the
   * second with `rootchild`, which is. The third ends with `foo()later`,
   * which holds a call.
   */
  public function joinedWords(): int {
    $a = 1;
    // root
    // Child items follow.
    $b = 2;
    // @mago-expect lint:drupal/inline-comment(1)
    // root
    // child items follow.
    $c = 3;
    // See foo()
    // later
    return $a + $b + $c;
  }

  /**
   * Skips the comment lines Coder skips.
   */
  public function skippedComments(bool $flag): bool {
    if ($flag) {
      $flag = FALSE;
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
