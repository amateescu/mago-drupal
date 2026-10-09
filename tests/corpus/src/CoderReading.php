<?php

declare(strict_types=1);

namespace Drupal\corpus;

/**
 * Reads tags and descriptions the way Coder does. Only the pinned cases report.
 */
final class CoderReading {

  // @mago-expect lint:drupal/doc-comment
  /**
   * The label, with its tag one space too deep.
   *
   *  @var string
   */
  public string $label = '';

  /**
   * {@inheritDoc}
   */
  public function inheritsInCamelCase(): void {
  }

  /**
   * Ends a parameter description before a phpcs line.
   *
   * @param string $value
   *   The value, which ends here.
   *
   * phpcs:ignore Drupal.Commenting.FunctionComment.Missing
   */
  public function directiveAfterParam(string $value): void {
  }

  /**
   * Has a long description after a directive line.
   *
   * phpcs:disable Drupal.Commenting.DocComment.LongNotCapital
   * Starts with a capital letter.
   */
  public function directiveBeforeDescription(): void {
  }

  /**
   * Describes the return value on the line below its type.
   *
   * @return $this
   *   $this, for chaining.
   */
  public function returnsItself(): static {
    return $this;
  }

  /**
   * Describes a reference on the line below it.
   *
   * @see self::returnsItself()
   *   Which returns the object.
   */
  public function seeWithDescription(): void {
  }

  /**
   * Was replaced.
   *
   * @deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use
   *   returnsItself() instead.
   *
   * @see https://www.drupal.org/node/3456789
   *   cspell:ignore returns
   */
  public function deprecatedWithNote(): void {
  }

  /**
   * Keeps a commented-out docblock and a first-class callable.
   */
  public function commentedDocblock(): callable {
    // /** @var int $old */
    return \Drupal\corpus\coder_reading_helper(...);
  }

}

/**
 * Returns nothing.
 */
function coder_reading_helper(): void {
}
