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
   * In a comment over several lines, the line form covers the last line.
   */
  public function lineFormInLongComment(): int {
    // @mago-expect analysis:invalid-return-statement
    return 'not an integer'; /* The caller casts it.
     * @phpstan-ignore-line */
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
   * The comment stops at the end of an alternative syntax block.
   */
  public function deadCodeInAlternativeSyntax(bool $flag): void {
    if ($flag):
      return;
      // @phpstan-ignore deadCode.unreachable
      $flag = FALSE;
    endif;
    $flag = TRUE;
    return;
    // @mago-expect analysis:unevaluated-code
    $flag = FALSE;
  }

  /**
   * The comment stops at an alternative syntax `else`.
   */
  public function deadCodeBeforeAlternativeElse(bool $flag): void {
    if ($flag):
      return;
      // @phpstan-ignore deadCode.unreachable
      $flag = FALSE;
    else:
      return;
      // @mago-expect analysis:unevaluated-code
      $flag = TRUE;
    endif;
  }

  /**
   * The comment stops at the next `case` label.
   */
  public function deadCodeInSwitch(int $value): void {
    switch ($value) {
      case 1:
        return;
        // @phpstan-ignore deadCode.unreachable
        $value = 2;

      case 2:
        return;
        // @mago-expect analysis:unevaluated-code
        $value = 3;
    }
  }

  /**
   * A block that the covered line opens stays in the dead run.
   */
  public function deadCodeOpensBlock(array $items): void {
    return;
    // @phpstan-ignore deadCode.unreachable
    foreach ($items as $item):
      $item++;
    endforeach;
    foreach ($items as $item) {
      $item++;
    }
    $items = [];
  }

  /**
   * A block that the covered line opens before the dead code ends the run.
   */
  public function deadCodeAfterOpenerOnLine(bool $flag): void {
    // @mago-format-ignore-start
    if ($flag): return; $flag = FALSE; // @phpstan-ignore deadCode.unreachable
    endif;
    return;
    // @mago-expect analysis:unevaluated-code
    $flag = TRUE;
    // @mago-format-ignore-end
  }

  /**
   * The same holds for a brace on the covered line.
   */
  public function deadCodeAfterBraceOnLine(bool $flag): void {
    // @mago-format-ignore-start
    if ($flag) { return; $flag = FALSE; // @phpstan-ignore deadCode.unreachable
    }
    return;
    // @mago-expect analysis:unevaluated-code
    $flag = TRUE;
    // @mago-format-ignore-end
  }

  /**
   * And for a comment on the line above the covered line.
   */
  public function deadCodeAfterOpenerBelowComment(bool $flag): void {
    // @mago-format-ignore-start
    // @phpstan-ignore deadCode.unreachable
    if ($flag): return; $flag = FALSE;
    endif;
    return;
    // @mago-expect analysis:unevaluated-code
    $flag = TRUE;
    // @mago-format-ignore-end
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

/**
 * Calls an instance method statically, which PHPStan names `method.staticCall`.
 */
function ignore_comments_static_call(): void {
  // @phpstan-ignore method.staticCall
  IgnoreComments::identifier();
}
