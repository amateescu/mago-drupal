<?php

declare(strict_types=1);

namespace Drupal\corpus;

/**
 * Variable names that start with a capital letter.
 */
class VariableName {

  /**
   * A static property, which drupal/property-name checks.
   *
   * @var int
   */
  // @mago-expect lint:drupal/property-name
  public static int $Count = 0;

  /**
   * A promoted constructor parameter is a parameter here.
   */
  // @mago-expect lint:drupal/variable-name
  public function __construct(
    protected int $Promoted = 1,
  ) {}

  /**
   * Each occurrence is reported.
   */
  // @mago-expect lint:drupal/variable-name
  public function parameter(int $Param): int {
    // @mago-expect lint:drupal/variable-name(2)
    $Total = $Param + 1;
    // @mago-expect lint:drupal/variable-name
    return $Total;
  }

  /**
   * Loop, catch, static and global variables.
   *
   * @param array<string, string> $items
   *   The items.
   */
  public function declarations(array $items): string {
    $text = '';
    // @mago-expect lint:drupal/variable-name(2)
    foreach ($items as $Key => $Value) {
      // @mago-expect lint:drupal/variable-name(2)
      $text .= $Key . $Value;
    }

    try {
      $text .= 'x';
    }
    // @mago-expect lint:drupal/variable-name
    catch (\Exception $E) {
      // @mago-expect lint:drupal/variable-name
      $text .= $E->getMessage();
    }

    // @mago-expect lint:drupal/variable-name
    static $Cache = [];
    // @mago-expect lint:drupal/global-variable
    // @mago-expect lint:drupal/variable-name
    global $User;
    // @mago-expect lint:drupal/variable-name(2)
    return $text . count($Cache) . gettype($User);
  }

  /**
   * Closures, arrow functions and destructuring.
   */
  public function closures(): array {
    // @mago-expect lint:drupal/variable-name
    [$A, $b] = [1, 2];
    // @mago-expect lint:drupal/variable-name
    $add = function (int $c) use ($A): int {
      // @mago-expect lint:drupal/variable-name
      return $A + $c;
    };
    // @mago-expect lint:drupal/variable-name(2)
    $double = fn (int $X): int => $X * 2;

    return [$add($b), $double($b)];
  }

  /**
   * The names that Coder skips.
   */
  public function skipped(): string {
    $name = 'x';
    $É = 1;
    $_under = 2;
    // A static property after `::`.
    $count = self::$Count + static::$Count;
    // Variables in a double-quoted string or a heredoc.
    $text = "{$name} $name";
    $doc = <<<EOT
      {$text}
      EOT;

    return count($GLOBALS) . count($_SERVER) . $É . $_under . $count . $doc;
  }

  /**
   * A dynamic property name is a variable too.
   */
  public function dynamic(\stdClass $object): mixed {
    // @mago-expect lint:drupal/variable-name
    $Prop = 'x';
    // @mago-expect lint:drupal/variable-name
    return $object->$Prop;
  }

}
