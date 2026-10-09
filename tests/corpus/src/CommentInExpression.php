<?php

declare(strict_types=1);

namespace Drupal\corpus;

/**
 * Exercises the comment-in-expression rule.
 */
class CommentInExpression {

  // @mago-format-ignore-start
  /**
   * A block comment right after a cast, with and without spaces.
   *
   * @return array<int>
   *   The values.
   */
  public function blockComments(string $text): array {
    // @mago-expect lint:drupal/comment-in-expression
    $one = (int) /* One. */ $text;
    // @mago-expect lint:drupal/comment-in-expression
    $two = (int)/* Two. */$text;
    // @mago-expect lint:drupal/comment-in-expression
    $three = (int) /** Three. */ $text;
    // @mago-expect lint:drupal/comment-in-expression
    $four = (int) /* Four. */ /* Five. */ $text;

    return [$one, $two, $three, $four];
  }

  /**
   * A line comment after a cast, on the next line.
   */
  public function lineComments(string $text): int {
    // @mago-expect lint:drupal/comment-in-expression
    $one = (int)
      // One.
      $text;
    // @mago-expect lint:drupal/comment-in-expression
    // @mago-expect lint:drupal/inline-comment
    $two = (int)
      # Two.
      $text;

    return $one + $two;
  }
  // @mago-format-ignore-end

  /**
   * Every cast spelling that PHP reads is checked.
   *
   * @return array<mixed>
   *   The values.
   */
  public function spellings(int|string $value): array {
    return [
      // @mago-expect lint:drupal/comment-in-expression
      (int) /* A. */ $value,
      // @mago-expect lint:drupal/comment-in-expression
      (integer) /* B. */ $value,
      // @mago-expect lint:drupal/comment-in-expression
      (bool) /* C. */ $value,
      // @mago-expect lint:drupal/comment-in-expression
      (boolean) /* D. */ $value,
      // @mago-expect lint:drupal/comment-in-expression
      (float) /* E. */ $value,
      // @mago-expect lint:drupal/comment-in-expression
      (double) /* F. */ $value,
      // @mago-expect lint:drupal/comment-in-expression
      (string) /* G. */ $value,
      // @mago-expect lint:drupal/comment-in-expression
      (binary) /* H. */ $value,
      // @mago-expect lint:drupal/comment-in-expression
      (array) /* I. */ $value,
      // @mago-expect lint:drupal/comment-in-expression
      (object) /* J. */ $value,
    ];
  }

  /**
   * A comment after the inner of two casts, and between two casts.
   */
  public function nested(string $text): string {
    // @mago-expect lint:drupal/comment-in-expression
    $inner = (string) (int) /* One. */ $text;
    // @mago-expect lint:drupal/comment-in-expression
    $outer = (string) /* Two. */ (int) $text;

    return $inner . $outer;
  }

  /**
   * A cast in other places in an expression.
   *
   * @return array<int|string>
   *   The values.
   */
  public function contexts(string $text, bool $flag): array {
    $values = [];
    // @mago-expect lint:drupal/comment-in-expression
    $values[] = $flag ? (int) /* One. */ $text : 0;
    // @mago-expect lint:drupal/comment-in-expression
    $values[] = abs((int) /* Two. */ $text);
    // @mago-expect lint:drupal/comment-in-expression
    foreach ((array) /* Three. */ $text as $item) {
      $values[] = $item;
    }
    $values[] = match ($flag) {
      // @mago-expect lint:drupal/comment-in-expression
      TRUE => (int) /* Four. */ $text,
      FALSE => 0,
    };

    return $values;
  }

  // @mago-format-ignore-start
  /**
   * A cast with no comment, or a comment somewhere else, is fine.
   *
   * @return array<mixed>
   *   The values.
   */
  public function fine(string $text): array {
    $one = /* One. */ (int) $text;
    $two = (int) $text /* Two. */;
    $three = (int) ($text /* Three. */);
    $four = (int) ( /* Four. */ $text);
    $five = (int) $text;
    $six = 'Cast (int) /* Six. */ $text';
    $seven = <<<TEXT
      (int) /* Seven. */ $text
      TEXT;

    return [$one, $two, $three, $four, $five, $six, $seven];
  }

  /**
   * A comment between yield and from.
   *
   * @return \Generator
   *   The values.
   */
  public function yieldComments(\Generator $generator): \Generator {
    // @mago-expect lint:drupal/comment-in-expression
    yield /* One. */ from $generator;
    // @mago-expect lint:drupal/comment-in-expression
    yield/* Two. */from $generator;
    // @mago-expect lint:drupal/comment-in-expression
    yield /** Three. */ from $generator;
    // @mago-expect lint:drupal/comment-in-expression
    yield /* Four. */ /* Five. */ from $generator;
    // @mago-expect lint:drupal/comment-in-expression
    // @mago-expect lint:drupal/post-statement-comment
    yield // Six.
      from $generator;
    // @mago-expect lint:drupal/comment-in-expression
    yield
      // Seven.
      from $generator;
  }
  // @mago-format-ignore-end

  /**
   * A comment between yield and from in an arrow function.
   */
  public function arrowFunction(\Generator $generator): callable {
    // @mago-format-ignore-start
    // @mago-expect lint:drupal/comment-in-expression
    return fn () => yield /* One. */ from $generator;
    // @mago-format-ignore-end
  }

  // @mago-format-ignore-start
  /**
   * A yield from with no comment between the words, or a plain yield, is fine.
   *
   * @return \Generator
   *   The values.
   */
  public function yieldFine(\Generator $generator): \Generator {
    yield from $generator;
    yield from $generator /* One. */;
    yield from /* Two. */ $generator;
    yield /* Three. */ 1;
    yield
      from $generator;
    yield from [1, 2];
  }
  // @mago-format-ignore-end

}
