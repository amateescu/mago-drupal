<?php

declare(strict_types=1);

namespace Drupal\corpus;

/**
 * Exercises the redundant-return rule.
 */
trait RedundantReturnTrait {

  /**
   * Ends with a bare return.
   */
  public function traitMethod(): void {
    // @mago-expect lint:drupal/redundant-return
    return;
  }

}

/**
 * Exercises the redundant-return rule.
 */
enum RedundantReturnEnum {

  case One;

  /**
   * Ends with a bare return.
   */
  public function enumMethod(): void {
    // @mago-expect lint:drupal/redundant-return
    return;
  }

}

/**
 * Exercises the redundant-return rule.
 */
class RedundantReturn {

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
   * Ends with a bare return.
   */
  public function method(): void {
    $this->step();
    // @mago-expect lint:drupal/redundant-return
    return;
  }

  /**
   * Ends with a bare return.
   */
  public static function staticMethod(): void {
    // @mago-expect lint:drupal/redundant-return
    return;
  }

  /**
   * Ends with a bare return after an if.
   */
  public function afterIf(): void {
    if ($this->ready()) {
      $this->step();
    }
    // @mago-expect lint:drupal/redundant-return
    return;
  }

  /**
   * Ends with a bare return that an earlier one in an if does not count for.
   */
  public function twoReturns(): void {
    if ($this->ready()) {
      return;
    }

    $this->step();
    // @mago-expect lint:drupal/redundant-return
    return;
  }

  /**
   * A comment after the statement stays.
   */
  public function commentAfter(): void {
    $this->step();
    // @mago-expect lint:drupal/redundant-return
    return;

    // Nothing follows.
  }

  /**
   * A comment inside the statement is reported without a fix.
   */
  public function commentInside(): void {
    $this->step();
    // @mago-format-ignore-start
    // @mago-expect lint:drupal/redundant-return
    return /* Done. */;
    // @mago-format-ignore-end
  }

  // @mago-format-ignore-start
  /**
   * A method on one line.
   */
  // @mago-expect lint:drupal/redundant-return
  public function oneLine(): void { return; }
  // @mago-format-ignore-end

  /**
   * A closure that ends with a bare return, and one that returns a value.
   *
   * @return array<callable>
   *   The closures.
   */
  public function closures(): array {
    $plain = function (): void {
      $this->step();
      // @mago-expect lint:drupal/redundant-return
      return;
    };
    $static = static function (): void {
      // @mago-expect lint:drupal/redundant-return
      return;
    };
    $value = function (): int {
      return 1;
    };
    $arrow = fn (): int => 1;

    return [$plain, $static, $value, $arrow];
  }

  /**
   * A closure inside a closure is checked on its own.
   */
  public function nestedClosures(): callable {
    return function (): callable {
      return function (): void {
        // @mago-expect lint:drupal/redundant-return
        return;
      };
    };
  }

  /**
   * A method of an anonymous class.
   */
  public function anonymousClass(): object {
    return new class() {

      /**
       * Ends with a bare return.
       */
      public function run(): void {
        // @mago-expect lint:drupal/redundant-return
        return;
      }

    };
  }

  /**
   * A generator that ends with a bare return.
   */
  public function generator(): \Generator {
    yield 1;
    // @mago-expect lint:drupal/redundant-return
    return;
  }

  // @mago-format-ignore-start
  /**
   * A bare return that ends a block at the end of the body.
   */
  public function endOfBlock(): void {
    $this->step();
    {
      // @mago-expect lint:drupal/redundant-return
      return;
    }
  }
  // @mago-format-ignore-end

  /**
   * A bare return that does not end the body is fine.
   */
  public function insideBranches(int $value): void {
    if ($this->ready()) {
      return;
    }

    if ($value > 1) {
      $this->step();
    }
    elseif ($value > 0) {
      return;
    }
    else {
      return;
    }

    foreach ([1, 2] as $item) {
      return;
    }

    while ($this->ready()) {
      return;
    }

    for ($i = 0; $i < $value; $i++) {
      return;
    }

    do {
      return;
    } while ($this->ready());
  }

  /**
   * A bare return that ends a try or a catch block is fine.
   */
  public function insideTry(): void {
    try {
      $this->step();
      return;
    }
    catch (\Exception) {
      return;
    }
  }

  /**
   * A bare return that ends a switch case is fine.
   */
  public function insideSwitch(int $value): void {
    switch ($value) {
      case 1:
        return;

      default:
        return;
    }
  }

  /**
   * A bare return in a finally block.
   */
  public function insideFinally(): void {
    try {
      $this->step();
    }
    finally {
      return;
    }
  }

  /**
   * A return with a value is fine.
   */
  public function withValue(): ?int {
    return NULL;
  }

  /**
   * A return that code follows is not the end of the body.
   */
  public function followed(): void {
    return;
    // @mago-expect analysis:unevaluated-code
    $this->step();
  }

  /**
   * The text of a comment or a string is not a return.
   */
  public function notCode(): string {
    // This return; is not code.
    /* return; */
    return 'return;';
  }

  // @mago-format-ignore-start
  /**
   * A return that is the whole body of an if, an else or a loop is fine.
   */
  public function unbraced(int $value): void {
    $this->step();
    if ($value > 1) return;
    elseif ($value > 0) return;
    else return;
  }

  /**
   * A return that is the body of a loop is not removable.
   */
  public function unbracedLoop(): void {
    while ($this->ready()) return;
  }
  // @mago-format-ignore-end

}

/**
 * Ends with a bare return.
 */
function redundant_return_function(): void {
  // @mago-expect lint:drupal/redundant-return
  return;
}

// A function declared inside an if.
if (!function_exists('redundant_return_conditional')) {
  /**
   * Ends with a bare return.
   */
  function redundant_return_conditional(): void {
    // @mago-expect lint:drupal/redundant-return
    return;
  }
}
