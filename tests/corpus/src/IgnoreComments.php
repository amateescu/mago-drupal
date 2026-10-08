<?php

declare(strict_types=1);

// PHPStan ignore comments, which the phpstan-ignores plugin honours.
namespace Drupal\corpus;

use Drupal\corpus\Legacy\RetiredThing;

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
   * More identifiers may follow a reason.
   */
  public function afterReason(): int {
    // @phpstan-ignore argument.type (the caller casts it), return.type
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
   * PHPStan 2 names a deprecated class after where it is used.
   */
  public function deprecatedClass(): void {
    // @phpstan-ignore new.deprecatedClass
    (new RetiredThing())->stillHere();
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

  /**
   * A comment on the first unreachable statement covers the rest of the block.
   *
   * PHPStan reports the block once, Mago once per statement.
   */
  public function deadCode(): int {
    return 1;
    // @phpstan-ignore deadCode.unreachable
    $value = 2;
    $value++;
    return $value;
  }

  /**
   * The comment stops at the end of its block.
   */
  public function deadCodeInBranches(bool $flag): int {
    if ($flag) {
      return 1;
      // @phpstan-ignore deadCode.unreachable
      $flag = FALSE;
    }
    else {
      return 2;
      // @mago-expect analysis:unevaluated-code
      $flag = TRUE;
    }
  }

  /**
   * A line form on reachable code does not start a dead run.
   */
  public function deadCodeAfterLineForm(): int {
    // @phpstan-ignore-next-line
    $value = strlen('text');
    throw new \RuntimeException((string) $value);
    // @mago-expect analysis:unevaluated-code
    return 1;
  }

}
