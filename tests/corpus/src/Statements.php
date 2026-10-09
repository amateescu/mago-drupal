<?php

declare(strict_types=1);

namespace Drupal\corpus;

// @mago-expect lint:drupal/function-comment
function list_plain(): int {
  // @mago-expect lint:drupal/short-list
  list($a, $b) = [1, 2];
  return $a + $b;
}

// @mago-expect lint:drupal/function-comment
function list_keyed(): string {
  // @mago-expect lint:drupal/short-list
  list('id' => $id, 'name' => $name) = ['id' => 1, 'name' => 'a'];
  return $id . $name;
}

// @mago-expect lint:drupal/function-comment
function list_skipped(): int {
  // @mago-expect lint:drupal/short-list
  list($a, , $c) = [1, 2, 3];
  return $a + $c;
}

// @mago-expect lint:drupal/function-comment
function list_nested(): int {
  // @mago-expect lint:drupal/short-list(2)
  list($a, list($b, $c)) = [1, [2, 3]];
  return $a + $b + $c;
}

// @mago-expect lint:drupal/function-comment
function list_in_foreach(): int {
  $sum = 0;
  // @mago-expect lint:drupal/short-list
  foreach ([[1, 'a']] as list($id, $name)) {
    $sum += $id + strlen($name);
  }
  // @mago-expect lint:drupal/short-list
  foreach ([[1]] as $key => list($id)) {
    $sum += $id + $key;
  }
  return $sum;
}

// @mago-expect lint:drupal/function-comment
function list_in_condition(int $x): int {
  // @mago-expect lint:drupal/short-list
  if ((list($a, $b) = [$x, 2]) !== [0, 0]) {
    return $a + $b;
  }
  return 0;
}

// @mago-expect lint:drupal/function-comment
function list_keyword_forms(): int {
  // @mago-format-ignore-start
  // @mago-expect lint:drupal/short-list
  LIST($a) = [1];
  // @mago-expect lint:drupal/short-list
  List ($b) = [2];
  // @mago-expect lint:drupal/short-list
  list /* gap */ ($c) = [3];
  // @mago-format-ignore-end
  return $a + $b + $c;
}

// @mago-expect lint:drupal/function-comment
function list_near_misses(): int {
  [$a, $b] = [1, 2];
  $holder = new ListNames();
  $count = $holder->list() + count(value: [1]);
  // The list( text in a comment is not a destructuring.
  $text = 'list($x) = $y';
  return $a + $b + $count + strlen($text);
}

/**
 * Holds names that look like the keyword.
 */
class ListNames {

  /**
   * The list constant.
   */
  const LIST_SIZE = 1;

  /**
   * The list property.
   *
   * @var array<int, int>
   */
  public array $list = [];

  /**
   * The list method.
   */
  public function list(): int {
    return count($this->list);
  }

}

// @mago-expect lint:drupal/function-comment
function property_names(): int {
  $holder = new PropertyHolder();
  return $holder->first + $holder->second;
}

/**
 * Marks a property.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Marker {}

/**
 * Declares properties one per statement and several per statement.
 */
class PropertyHolder {

  // @mago-expect lint:drupal/property-per-statement
  /**
   * Two untyped properties.
   *
   * @var int
   */
  public $first, $second;

  // @mago-expect lint:drupal/property-per-statement
  /**
   * Two typed properties with defaults.
   */
  private int $typed = 1, $other = 2;

  // @mago-expect lint:drupal/property-per-statement
  /**
   * Three static properties.
   */
  protected static ?int $a, $b, $c;

  // @mago-expect lint:drupal/property-per-statement
  /**
   * Two readonly properties under an attribute.
   */
  #[Marker]
  public readonly string $x, $y;

  // @mago-expect lint:drupal/property-per-statement
  /**
   * Two properties with an array default.
   */
  public array
    $near,
    $miss = [
      'a',
      'b',
    ]
  ;

  /**
   * One property.
   */
  public int $alone = 1;

  /**
   * Another property.
   *
   * @var array<int, int>
   */
  public $defaulted = [1, 2];

  /**
   * A constant list is not a property list.
   */
  const ONE = 1, TWO = 2;

  /**
   * Reads the properties.
   *
   * @return array<int, mixed>
   */
  public function all(): array {
    return [
      $this->first,
      $this->second,
      $this->typed,
      $this->other,
      $this->near,
      $this->miss,
      $this->x,
      $this->y,
      self::$a,
      self::$b,
      self::$c,
    ];
  }

  /**
   * A method with variables in its body.
   */
  public function body(int $a, int $b): int {
    static $cache = 0, $other = 1;
    $closure = function () use ($a, $b): int {
      return $a + $b;
    };
    return $closure() + $cache + $other;
  }

}

// @mago-expect lint:drupal/function-comment
function anonymous_class_properties(): object {
  return new class() {

    // @mago-expect lint:drupal/property-per-statement
    /**
     * Two properties.
     *
     * @var int
     */
    public $a, $b;

  };
}

// @mago-expect lint:drupal/function-comment
function switch_semicolons(int $x): int {
  switch ($x) {
    // @mago-expect lint:drupal/case-semicolon
    case 1;
      return 1;

    case 2:
    // @mago-expect lint:drupal/case-semicolon
    case 3;
      return 3;

    default:
      return 0;
  }
}

// @mago-expect lint:drupal/function-comment
function switch_default_semicolon(int $x): int {
  switch ($x) {
    case 1:
      return 1;

    // @mago-expect lint:drupal/case-semicolon
    default;
      return 0;
  }
}

// @mago-expect lint:drupal/function-comment
function switch_label_shapes(int $x): int {
  // @mago-format-ignore-start
  switch ($x) {
    // @mago-expect lint:drupal/case-semicolon
    case 1 ;
      return 1;

    // @mago-expect lint:drupal/case-semicolon
    case 2
    ;
      return 2;

    // @mago-expect lint:drupal/case-semicolon
    CASE 3;
      return 3;

    // @mago-expect lint:drupal/case-semicolon
    case ($x > 4 ? 5 : 6) ;
      return 4;

    // @mago-expect lint:drupal/case-semicolon
    DEFAULT ;
      return 0;
  }
  // @mago-format-ignore-end
}

// @mago-expect lint:drupal/function-comment
function switch_alternative_syntax(int $x): int {
  switch ($x):
    // @mago-expect lint:drupal/case-semicolon
    case 1;
      return 1;
    default:
      return 0;
  endswitch;
}

// @mago-expect lint:drupal/function-comment
function switch_ternary_label(int $x): int {
  switch ($x) {
    // @mago-expect lint:drupal/case-semicolon
    case $x ? 1 : 2;
      return 1;

    case 3:
      return 3;

    default:
      return 0;
  }
}

// @mago-expect lint:drupal/function-comment
function switch_nested_semicolon(int $x, int $y): int {
  switch ($x) {
    case 1:
      switch ($y) {
        // @mago-expect lint:drupal/case-semicolon
        case 2;
          return 2;

        default:
          return 3;
      }

    default:
      return 0;
  }
}

// @mago-expect lint:drupal/function-comment
function switch_colons(int $x, string $enum): int {
  switch ($x) {
    case 1:
      return 1;

    default:
      return match ($enum) {
        'a' => 1,
        default => 2,
      };
  }
}

// @mago-expect lint:drupal/function-comment
function switch_default_only(int $x): int {
  // @mago-expect lint:drupal/empty-switch
  switch ($x) {
    default:
      return 0;
  }
}

// @mago-expect lint:drupal/function-comment
function switch_empty_body(int $x): int {
  // @mago-expect lint:drupal/empty-switch
  switch ($x) {
  }
  return $x;
}

// @mago-expect lint:drupal/function-comment
function switch_empty_alternative(int $x): int {
  // @mago-expect lint:drupal/empty-switch
  switch ($x):
  endswitch;
  return $x;
}

// @mago-expect lint:drupal/function-comment
function switch_default_only_alternative(int $x): int {
  // @mago-expect lint:drupal/empty-switch
  switch ($x):
    default:
      return 0;
  endswitch;
}

// @mago-expect lint:drupal/function-comment
function switch_uppercase_keywords(int $x): int {
  // @mago-format-ignore-start
  // @mago-expect lint:drupal/empty-switch
  SWITCH ($x) {
    DEFAULT:
      return 0;
  }
  // @mago-format-ignore-end
}

// @mago-expect lint:drupal/function-comment
function switch_inner_empty(int $x, int $y): int {
  switch ($x) {
    case 1:
      // @mago-expect lint:drupal/empty-switch
      switch ($y) {
        default:
          return 2;
      }

    default:
      return 0;
  }
}

// @mago-expect lint:drupal/function-comment
function switch_only_inner_cases(int $x, int $y): int {
  // @mago-expect lint:drupal/empty-switch
  switch ($x) {
    default:
      switch ($y) {
        case 1:
          return 1;

        default:
          return 0;
      }
  }
}

// @mago-expect lint:drupal/function-comment
function switch_with_case(int $x): int {
  switch ($x) {
    case 1:
      return 1;
  }
  return 0;
}

// @mago-expect lint:drupal/function-comment
function switch_uppercase_case(int $x): int {
  // @mago-format-ignore-start
  switch ($x) {
    CASE 1:
      return 1;

    default:
      return 0;
  }
  // @mago-format-ignore-end
}

// @mago-expect lint:drupal/function-comment
function switch_named_arguments(int $x): int {
  // @mago-expect lint:drupal/empty-switch
  switch ($x) {
    default:
      return strlen(string: 'case 1:');
  }
}
