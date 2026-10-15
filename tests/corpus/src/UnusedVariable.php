<?php

declare(strict_types=1);

namespace Drupal\corpus;

/**
 * Exercises drupal/unused-variable in methods and closures.
 */
class UnusedVariable {

  /**
   * A property is in no scope.
   *
   * @var int
   */
  public int $count = 0;

  /**
   * A static property is in no scope.
   *
   * @var int
   */
  protected static int $total = 0;

  /**
   * Constructs an UnusedVariable object.
   *
   * @param int $promoted
   *   A promoted parameter.
   */
  public function __construct(
    protected int $promoted,
  ) {}

  /**
   * Returns a value.
   */
  protected function pick(): int {
    return 1;
  }

  /**
   * Takes values.
   */
  protected function take(mixed ...$values): void {
  }

  /**
   * Returns a list.
   *
   * @return list<int>
   *   Two numbers.
   */
  protected function pair(): array {
    return [1, 2];
  }

  /**
   * Writes a variable once.
   */
  public function written(): void {
    // @mago-expect lint:drupal/unused-variable
    $unused = $this->pick();
  }

  /**
   * Reports every write of a name that is never read.
   */
  public function writtenTwice(): void {
    // @mago-expect lint:drupal/unused-variable
    $value = 1;
    // @mago-expect lint:drupal/unused-variable
    $value = 2;
  }

  /**
   * Reports a compound assignment, which doesn't count as a read.
   */
  public function compound(): void {
    // @mago-expect lint:drupal/unused-variable
    $text = 'a';
    // @mago-expect lint:drupal/unused-variable
    $text .= 'b';
    // @mago-expect analysis:mixed-assignment
    // @mago-expect lint:drupal/unused-variable
    $default ??= 'c';
  }

  /**
   * Counts an increment, isset() and unset() as reads.
   */
  public function reads(): bool {
    $count = 0;
    $count++;
    $step = 0;
    ++$step;
    $gone = 1;
    unset($gone);
    $set = 1;

    return isset($set);
  }

  /**
   * Counts a read anywhere in the scope, before or after the write.
   */
  public function readBefore(): void {
    $value = 1;
    $this->take($value);
    $value = 2;
  }

  /**
   * Allows an unused catch variable, and its name for the whole scope.
   */
  public function caught(): void {
    $e = 1;
    try {
      $this->pick();
    }
    // @mago-expect lint:drupal/non-capturing-catch
    catch (\Exception $e) {
      return;
    }
  }

  /**
   * Reports an unused foreach key.
   */
  public function foreachKey(): void {
    // @mago-expect lint:drupal/unused-variable
    foreach ($this->pair() as $key => $item) {
      $this->take($item);
    }
  }

  /**
   * Allows an unused foreach value next to a key.
   */
  public function foreachValueWithKey(): void {
    foreach ($this->pair() as $key => $item) {
      $this->take($key);
    }
  }

  /**
   * Reports an unused foreach value without a key.
   */
  public function foreachValue(): void {
    // @mago-expect lint:drupal/unused-variable
    foreach ($this->pair() as $item) {
      $this->take();
    }
  }

  /**
   * Reports a foreach reference that nothing touches.
   *
   * @param list<int> $items
   *   The items.
   */
  public function foreachReference(array &$items): void {
    // @mago-expect lint:drupal/unused-variable
    foreach ($items as &$item) {
      $this->take();
    }
  }

  /**
   * Allows a foreach reference written through.
   *
   * @param list<int> $items
   *   The items.
   */
  public function foreachReferenceWritten(array &$items): void {
    foreach ($items as &$item) {
      $item = 0;
    }
  }

  /**
   * Allows a foreach reference value next to a key.
   *
   * @param list<int> $items
   *   The items.
   */
  public function foreachReferenceWithKey(array &$items): void {
    foreach ($items as $key => &$item) {
      $this->take($key);
    }
  }

  /**
   * Reports an unused element of a foreach list.
   *
   * @param list<list<int>> $rows
   *   The rows.
   */
  public function foreachList(array $rows): void {
    // @mago-expect lint:drupal/unused-variable
    foreach ($rows as [$first, $second]) {
      $this->take($first);
    }
  }

  /**
   * Reports unused list elements.
   */
  public function lists(): int {
    // @mago-expect lint:drupal/unused-variable
    [$a, $b] = $this->pair();
    // @mago-expect lint:drupal/short-list
    // @mago-expect lint:drupal/unused-variable
    list($c, $d) = $this->pair();
    // @mago-expect lint:drupal/unused-variable
    [$e, [$f, $g]] = [1, [2, 3]];
    // @mago-expect lint:drupal/unused-variable
    ['x' => $h, 'y' => $i] = ['x' => 1, 'y' => 2];

    return $a + $c + $e + $g + $h;
  }

  /**
   * Reports a list element by reference.
   *
   * @param list<int> $source
   *   The source.
   */
  public function listReference(array $source): int {
    // @mago-expect analysis:invalid-assignment
    // @mago-expect lint:drupal/unused-variable
    [&$a, $b] = $source;

    return $b;
  }

  /**
   * Counts the array of an indexed list element as read.
   */
  public function listIndex(): int {
    $map = [];
    $key = 'a';
    [$map[$key], $b] = $this->pair();

    return $b;
  }

  /**
   * Reports an unused static variable.
   */
  public function staticVariables(): int {
    // @mago-expect lint:drupal/unused-variable
    static $cache;
    // @mago-expect lint:drupal/unused-variable
    static $seed = 1;
    // @mago-expect lint:drupal/unused-variable
    static $used = 0, $unused;

    return $used;
  }

  /**
   * Reports an unused global.
   */
  public function globals(): void {
    // @mago-expect lint:drupal/unused-variable
    global $_corpus_unused_global;
  }

  /**
   * Allows a global that the function assigns or writes through.
   */
  public function globalsWritten(): void {
    global $_corpus_assigned_global, $_corpus_indexed_global;
    $_corpus_assigned_global = 1;
    // @mago-expect analysis:mixed-array-assignment
    $_corpus_indexed_global['x'] = 1;
  }

  /**
   * Reports a variable of a closure's use list that the closure never reads.
   */
  public function closureBinding(): \Closure {
    $x = 1;

    // @mago-expect lint:drupal/unused-variable
    return function () use ($x): int {
      return 2;
    };
  }

  /**
   * Reports the binding and the write of a use variable that is never read.
   */
  public function closureBindingWritten(): \Closure {
    $x = 1;

    // @mago-expect lint:drupal/unused-variable
    return function () use ($x): void {
      // @mago-expect lint:drupal/unused-variable
      $x = 2;
    };
  }

  /**
   * Allows a use variable by reference.
   */
  public function closureReference(): int {
    $total = 0;
    $unread = 0;
    $add = function () use (&$total, &$unread): void {
      $total = 5;
    };
    $add();

    return $total;
  }

  /**
   * Counts a nested closure's use list as a read of the outer closure.
   */
  public function nestedClosures(): \Closure {
    $outer = 1;

    return static function () use ($outer): \Closure {
      return function () use ($outer): int {
        return $outer;
      };
    };
  }

  /**
   * Checks a closure as a scope of its own.
   */
  public function closureLocal(): \Closure {
    return function (): void {
      // @mago-expect lint:drupal/unused-variable
      $inner = 1;
    };
  }

  /**
   * Counts a closure argument's body as its own scope.
   */
  public function closureArgument(): void {
    $this->take(function (): void {
      // @mago-expect lint:drupal/unused-variable
      $inside = 1;
    });
  }

  /**
   * Reports a write in an arrow function in the enclosing scope.
   */
  public function arrowWrite(): \Closure {
    // @mago-expect lint:drupal/unused-variable
    return fn (): int => $written = 1;
  }

  /**
   * Counts a read in an arrow function for the enclosing scope.
   */
  public function arrowRead(): \Closure {
    $x = 1;

    return fn (): int => $x;
  }

  /**
   * Reads an arrow function's parameter, not the enclosing variable.
   */
  public function arrowParameter(): \Closure {
    // @mago-expect lint:drupal/unused-variable
    $x = 1;

    return fn (int $x): \Closure => fn (int $y): int => $x + $y;
  }

  /**
   * Counts the write of an assignment that is an argument as a read.
   */
  public function arrowArgument(): void {
    $this->take(fn (): int => $kept = 1);
  }

  /**
   * Reports a name with a leading underscore.
   */
  public function underscore(): void {
    // @mago-expect lint:drupal/unused-variable
    $_unused = $this->pick();
  }

  /**
   * Allows a reference that is written through.
   *
   * @param array<string, int> $data
   *   The data.
   * @param array<string, array<string, int>> $nested
   *   The nested data.
   */
  public function referenceWritten(array &$data, array &$nested): void {
    $ref = &$data['x'];
    $ref = 1;
    $index = &$nested['y'];
    $index['z'] = 1;
  }

  /**
   * Reports a reference that is bound and never used.
   *
   * @param array<string, int> $data
   *   The data.
   */
  public function referenceUnused(array &$data): void {
    // @mago-expect lint:drupal/unused-variable
    $ref = &$data['x'];
  }

  /**
   * Allows a reference that walks down an array, as in NestedArray.
   *
   * @param array<string, mixed> $tree
   *   The tree.
   * @param list<string> $keys
   *   The keys.
   */
  public function referenceWalk(array &$tree, array $keys): void {
    $target = &$tree;
    foreach ($keys as $key) {
      // @mago-expect analysis:mixed-array-access
      // @mago-expect analysis:mixed-assignment
      $target = &$target[$key];
    }
    $target = 1;
  }

  /**
   * Counts the names in interpolated strings as reads.
   */
  public function strings(): string {
    $a = 1;
    $b = 2;
    $c = 3;
    $d = 4;
    $e = 5;
    // @mago-expect lint:drupal/unused-variable
    $f = 6;
    print "{$a} {$b}";
    print <<<EOT
        $c {$d}
        EOT;
    print <<<'EOT'
        $f
        EOT;

    return "${e}";
  }

  /**
   * Counts the names in compact() as reads.
   *
   * @return array<string, int>
   *   The variables.
   */
  public function compacted(): array {
    $a = 1;
    $b = 2;
    $c = 3;

    return compact('a', ['b'], array('c'));
  }

  /**
   * Counts the variables used before get_defined_vars() as read.
   *
   * @return array<mixed>
   *   The variables.
   */
  public function definedVars(): array {
    $before = 1;
    $vars = get_defined_vars();
    // @mago-expect lint:drupal/unused-variable
    $after = 2;

    return $vars;
  }

  /**
   * Counts a write that is the value of another assignment as a read.
   */
  public function chained(): void {
    // @mago-expect lint:drupal/unused-variable
    $a = $b = 1;
    $this->count = $c = 2;
    $list = ['a' => 1, 'b' => $d = 2];
    $this->take($list);
  }

  /**
   * Counts a write in a call argument as a read.
   */
  public function argument(): void {
    $this->take($x = 1);
  }

  /**
   * Reports a write in a condition.
   */
  public function conditions(): void {
    // @mago-expect lint:drupal/unused-variable
    if ($found = $this->pick()) {
      return;
    }
    // @mago-expect lint:drupal/unused-variable
    while (($row = $this->pick()) !== 0) {
      $this->take();
    }
  }

  /**
   * Reports a write in a match arm or a ternary.
   */
  public function expressions(int $v): int {
    // @mago-expect lint:drupal/unused-variable
    $m = match ($v) {
      1 => $n = 2,
      default => 0,
    };

    // @mago-expect lint:drupal/unused-variable
    return $v ? ($t = 1) : 0;
  }

  /**
   * Counts writes to an index or a property as reads.
   */
  public function members(): void {
    $a = [];
    $a[] = 1;
    $b = [];
    $b['x'] = 1;
    $o = new \stdClass();
    $o->x = 1;
  }

  /**
   * Allows parameters, $this, superglobals and static properties.
   */
  public function allowed(int $unused, int &$reference, int $reassigned): void {
    $reassigned = 1;
    $reference = 2;
    $this->count = 1;
    self::$total = 1;
    static::$total = 2;
    $_SESSION['corpus'] = 1;
    $GLOBALS['corpus'] = 1;
  }

  /**
   * Counts the name in a variable variable as read.
   */
  public function variableVariable(): void {
    $name = 'x';
    // @mago-expect analysis:invalid-assignment
    $$name = 1;
  }

  /**
   * Reads the arguments of an anonymous class, and checks its methods.
   */
  public function anonymousClass(): object {
    $argument = 1;

    return new class($argument) {

      /**
       * Constructs the class.
       *
       * @param int $value
       *   A value.
       */
      public function __construct(
        public int $value,
      ) {}

      /**
       * Writes a local.
       */
      public function method(): void {
        // @mago-expect lint:drupal/unused-variable
        $local = 1;
      }

    };
  }

  /**
   * Reports a foreach key next to an array literal with a key.
   */
  public function literalKey(): void {
    // @mago-expect lint:drupal/unused-variable
    foreach (['x' => 1] as $key => $value) {
      $this->take($value);
    }
  }

  /**
   * Counts a read in an else branch, which a later loop pass reaches.
   *
   * @param list<int> $items
   *   The items.
   */
  public function elseBranch(array $items): void {
    $first = TRUE;
    foreach ($items as $item) {
      if ($first) {
        $previous = $item;
        $first = FALSE;
      }
      else {
        // @mago-expect analysis:possibly-undefined-variable
        $this->take($previous, $item);
      }
    }
  }

}
