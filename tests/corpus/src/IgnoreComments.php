<?php

/**
 * @file
 * PHPStan ignore comments, which the phpstan-ignores plugin honours.
 */

declare(strict_types=1);

namespace Drupal\corpus;

/**
 * Returns a string where an integer is declared, under each comment form.
 */
final class IgnoreComments {

  /**
   * An identifier drops the Mago issue that reports the same finding.
   */
  public function identifier(): int {
    // @phpstan-ignore return.type
    return 'not an integer';
  }

  /**
   * A reason in parentheses may follow the identifiers.
   */
  public function withReason(): int {
    // @phpstan-ignore argument.type, return.type (the caller casts it)
    return 'not an integer';
  }

  /**
   * A trailing comment covers its own line.
   */
  public function trailing(): int {
    return 'not an integer'; // @phpstan-ignore return.type
  }

  /**
   * The line forms drop every mapped code.
   */
  public function nextLine(): int {
    // @phpstan-ignore-next-line
    return 'not an integer';
  }

  /**
   * Another identifier keeps the issue.
   */
  public function otherIdentifier(): int {
    // @phpstan-ignore argument.type
    // @mago-expect analysis:invalid-return-statement
    return 'not an integer';
  }

  /**
   * An identifier the table does not map drops nothing.
   */
  public function unmapped(): int {
    // @phpstan-ignore phpstanApi.interface
    // @mago-expect analysis:invalid-return-statement
    return 'not an integer';
  }

  /**
   * A comment covers one line only.
   */
  public function oneLine(): int {
    // @phpstan-ignore return.type
    $value = 'not an integer';
    // @mago-expect analysis:invalid-return-statement
    return $value;
  }

}
