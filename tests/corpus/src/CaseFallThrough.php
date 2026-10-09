<?php

declare(strict_types=1);

namespace Drupal\corpus;

/**
 * Exercises the case-fall-through rule.
 *
 * A pragma above a case is a comment right before that case, which makes the
 * case above it fine. So a reported case is the first one, or follows a case
 * that ends with `break`.
 */
class CaseFallThrough {

  /**
   * Does one step.
   */
  protected function step(): void {
  }

  /**
   * Tells whether to go on.
   */
  protected function ready(): bool {
    return TRUE;
  }

  /**
   * Picks a value.
   */
  protected function pick(): int {
    return 1;
  }

  /**
   * A case with code and no ending or comment.
   */
  public function plain(int $value): void {
    switch ($value) {
      // @mago-expect lint:drupal/case-fall-through
      case 1:
        $this->step();
      case 2:
        $this->step();
        break;
    }
  }

  /**
   * Cases that end, or are empty, or are the last case.
   */
  public function ended(int $value): int {
    switch ($value) {
      case 1:
        $this->step();
        break;

      case 2:
      case 3:
        return 3;

      case 4:
        throw new \RuntimeException('four');

      case 5:
        $this->step();
        exit();

      case 6:
        return 6;

      default:
        $this->step();
    }

    return 0;
  }

  /**
   * A comment of any kind before the next case, and a comment-only case.
   */
  public function commented(int $value): void {
    switch ($value) {
      case 1:
        $this->step();
      // Falls through.
      case 2:
        $this->step();
      /* Falls through. */
      case 3:
        $this->step();
      /** Falls through. */
      case 4:
        $this->step();
      // Not a note about falling through.
      case 5:
      // Only a comment.
      case 6:
        $this->step();
        break;
    }
  }

  /**
   * A comment before the code is no note.
   */
  public function commentBeforeTheCode(int $value): void {
    switch ($value) {
      // @mago-expect lint:drupal/case-fall-through
      case 1:
        // Falls through.
        $this->step();
      case 2:
        break;

      case 3:
        // Falls through.
        $this->step();
        break;
    }
  }

  /**
   * The next label can be a default case, which is never reported itself.
   */
  public function beforeDefault(int $value): void {
    switch ($value) {
      // @mago-expect lint:drupal/case-fall-through
      case 1:
        $this->step();
      default:
        $this->step();
      case 2:
        $this->step();
        break;
    }
  }

  /**
   * Code after the statement that ends the case is not checked.
   */
  public function deadCode(int $value): int {
    switch ($value) {
      case 1:
        return 1;
        // @mago-expect analysis:unevaluated-code
        $this->step();
      case 2:
        break;
        // @mago-expect analysis:unevaluated-code
        $this->step();
      default:
        return 0;
    }

    return 2;
  }

  /**
   * An if chain ends the case when it has an else and every branch ends.
   */
  public function ifChain(int $value): int {
    switch ($value) {
      case 1:
        if ($this->ready()) {
          return 1;
        }
        else {
          return 2;
        }
      case 2:
        if ($this->ready()) {
          return 1;
        }
        elseif ($this->ready()) {
          return 3;
        }
        else {
          throw new \RuntimeException('two');
        }
      case 3:
        if ($this->ready()) {
          $this->step();
          return 1;
        }
        // @mago-expect lint:drupal/else-if
        else if ($this->ready()) {
          return 3;
        }
        else {
          return 4;
        }
      default:
        return 0;
    }
  }

  /**
   * An if without an else, or with a branch that does not end.
   */
  public function ifGaps(int $value): int {
    switch ($value) {
      // @mago-expect lint:drupal/case-fall-through
      case 1:
        if ($this->ready()) {
          return 1;
        }
      case 2:
        break;

      // @mago-expect lint:drupal/case-fall-through
      case 3:
        if ($this->ready()) {
          return 1;
        }
        elseif ($this->ready()) {
          return 3;
        }
      case 4:
        break;

      // @mago-expect lint:drupal/case-fall-through
      case 5:
        if ($this->ready()) {
          return 1;
        }
        else {
          $this->step();
        }
      case 6:
        break;

      // @mago-expect lint:drupal/case-fall-through
      case 7:
        if ($this->ready()) {
          return 1;
        }
        elseif ($this->ready()) {
          $this->step();
        }
        else {
          return 4;
        }
      default:
        return 0;
    }

    return 1;
  }

  /**
   * A return inside an if is not the end of the case.
   */
  public function returnInsideIf(int $value): int {
    switch ($value) {
      // @mago-expect lint:drupal/case-fall-through
      case 1:
        if ($this->ready()) {
          return 1;
        }
        $this->step();
      case 2:
        break;

      // @mago-expect lint:drupal/case-fall-through
      case 3:
        if ($this->ready()) {
          throw new \RuntimeException('three');
        }
        $this->step();
      default:
        return 0;
    }

    return 1;
  }

  /**
   * An if before the statement that ends the case does not matter.
   */
  public function endedAfterIf(int $value): int {
    switch ($value) {
      case 1:
        if ($this->ready()) {
          $this->step();
        }
        return 1;

      default:
        return 0;
    }
  }

  /**
   * A try ends the case through its finally block, or its try and catch blocks.
   */
  public function tryEnds(int $value): int {
    switch ($value) {
      case 1:
        try {
          return $this->pick();
        }
        catch (\Throwable $exception) {
          throw $exception;
        }
      case 2:
        try {
          $this->step();
        }
        finally {
          return 2;
        }
      case 3:
        try {
          return 3;
        }
        catch (\RuntimeException) {
          return 4;
        }
        finally {
          $this->step();
        }
      default:
        return 0;
    }
  }

  /**
   * A try with a block that does not end.
   */
  public function tryGaps(int $value): int {
    switch ($value) {
      // @mago-expect lint:drupal/case-fall-through
      case 1:
        try {
          return $this->pick();
        }
        catch (\Throwable) {
          $this->step();
        }
      case 2:
        break;

      // @mago-expect lint:drupal/case-fall-through
      case 3:
        try {
          $this->step();
        }
        catch (\Throwable) {
          return 2;
        }
      case 4:
        break;

      // @mago-expect lint:drupal/case-fall-through
      case 5:
        try {
          $this->step();
        }
        finally {
          $this->step();
        }
      default:
        return 0;
    }

    return 1;
  }

  /**
   * A nested switch ends the case when it has a default and every case ends.
   */
  public function nestedEnds(int $value): int {
    switch ($value) {
      case 1:
        switch ($this->pick()) {
          case 1:
            return 1;

          case 2:
          case 3:
            return 3;

          default:
            throw new \RuntimeException('one');
        }
      case 2:
        switch ($this->pick()) {
          case 1:
            break 2;

          default:
            return 2;
        }
      default:
        return 0;
    }
  }

  /**
   * A nested switch with no default, a last case with no code, or a break.
   */
  public function nestedGaps(int $value): int {
    switch ($value) {
      // @mago-expect lint:drupal/case-fall-through
      case 1:
        switch ($this->pick()) {
          case 1:
            return 1;

          case 2:
            return 2;
        }
      case 2:
        break;

      // @mago-expect lint:drupal/case-fall-through
      case 3:
        switch ($this->pick()) {
          case 1:
            break;

          default:
            return 3;
        }
      case 4:
        break;

      // @mago-expect lint:drupal/case-fall-through
      case 5:
        switch ($this->pick()) {
          case 1:
            return 1;

          default:
        }
      default:
        return 0;
    }

    return 1;
  }

  /**
   * Loops and closures do not end the case.
   */
  public function loopsAndClosures(int $value): void {
    switch ($value) {
      // @mago-expect lint:drupal/case-fall-through
      case 1:
        while ($this->pick() > 1) {
          return;
        }
      case 2:
        break;

      // @mago-expect lint:drupal/case-fall-through
      case 3:
        foreach ([1, 2] as $item) {
          return;
        }
      case 4:
        break;

      // @mago-expect lint:drupal/case-fall-through
      case 5:
        $callback = function (): int {
          return 3;
        };
      case 6:
        break;

      // @mago-expect lint:drupal/case-fall-through
      case 7:
        $callback = fn (): int => 4;
      case 8:
        break;

      // @mago-expect lint:drupal/case-fall-through
      case 9:
        $this->step();
        ?>
        <p>Markup</p>
        <?php
      default:
        $this->step();
    }
  }

  /**
   * An expression that holds a throw or an exit does not always end the case.
   */
  public function expressionForms(int $value): void {
    switch ($value) {
      // @mago-expect lint:drupal/case-fall-through
      case 1:
        $result = $this->pick() ?: throw new \RuntimeException('one');
      case 2:
        break;

      // @mago-expect lint:drupal/case-fall-through
      case 3:
        // @mago-expect analysis:unused-statement
        $this->ready() or exit();
      default:
        $this->step();
    }
  }

  // @mago-format-ignore-start
  /**
   * A block that holds the ending statement ends the case.
   */
  public function blocks(int $value): void {
    switch ($value) {
      case 1: {
        $this->step();
        return;
      }
      case 2: {
        $this->step();
        break;
      }
      default:
        $this->step();
    }
  }

  /**
   * A block with no ending statement falls through.
   */
  public function blockGaps(int $value): void {
    switch ($value) {
      // @mago-expect lint:drupal/case-fall-through
      case 1: {
        $this->step();
      }
      case 2:
        break;

      // @mago-expect lint:drupal/case-fall-through
      case 3: {
      }
      case 4:
        $this->step();
    }
  }
  // @mago-format-ignore-end

  /**
   * The alternative syntax is checked too.
   */
  public function alternativeSyntax(int $value): void {
    switch ($value):
      // @mago-expect lint:drupal/case-fall-through
      case 1:
        $this->step();
      case 2:
        $this->step();
        break;
      default:
        $this->step();
    endswitch;
  }

  /**
   * A case with a semicolon after its value is checked like one with a colon.
   */
  public function semicolonSeparator(int $value): void {
    switch ($value) {
      // @mago-expect lint:drupal/case-fall-through
      case 1;
        $this->step();
      case 2;
        $this->step();
        break;
    }
  }

  /**
   * A nested switch is checked on its own.
   */
  public function nestedFallThrough(int $value): void {
    switch ($value) {
      // The inner switch does not end the case, so the outer one is reported.
      // @mago-expect lint:drupal/case-fall-through
      case 1:
        switch ($this->pick()) {
          // @mago-expect lint:drupal/case-fall-through
          case 1:
            $this->step();
          default:
            return;
        }
      default:
        $this->step();
    }
  }

}
