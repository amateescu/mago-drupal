<?php

declare(strict_types=1);

namespace Drupal\corpus;

/**
 * Marks a parameter.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class ParameterBlankLineMarker {

  /**
   * Creates a marker.
   */
  public function __construct(
    public readonly string $name = '',
  ) {}

}

/**
 * Describes a method.
 */
interface ParameterBlankLineInterface {

  // @mago-format-ignore-start
  /**
   * A blank line in an interface method.
   */
  // @mago-expect lint:drupal/parameter-blank-line
  public function run(
    int $a,

    int $b,
  ): void;
  // @mago-format-ignore-end

}

/**
 * Exercises the parameter-blank-line rule.
 */
enum ParameterBlankLineEnum {

  case One;

  // @mago-format-ignore-start
  /**
   * A blank line in an enum method.
   */
  // @mago-expect lint:drupal/parameter-blank-line
  public function run(
    int $a,

    int $b,
  ): void {
  }
  // @mago-format-ignore-end

}

/**
 * Exercises the parameter-blank-line rule.
 */
abstract class ParameterBlankLine {

  // @mago-format-ignore-start
  /**
   * A blank line after the opening parenthesis.
   */
  // @mago-expect lint:drupal/parameter-blank-line
  public function afterOpening(

    int $a,
    int $b,
  ): void {
  }

  /**
   * A blank line between two parameters.
   */
  // @mago-expect lint:drupal/parameter-blank-line
  public function between(
    int $a,

    int $b,
  ): void {
  }

  /**
   * A blank line before the closing parenthesis.
   */
  // @mago-expect lint:drupal/parameter-blank-line
  public function beforeClosing(
    int $a,
    int $b,

  ): void {
  }

  /**
   * Two blank lines are two reports.
   */
  // @mago-expect lint:drupal/parameter-blank-line(2)
  public function two(
    int $a,


    int $b,
  ): void {
  }

  /**
   * A blank line in an empty parameter list.
   */
  // @mago-expect lint:drupal/parameter-blank-line
  public function emptyList(

  ): void {
  }

  /**
   * A line with only spaces is a blank line.
   */
  // @mago-expect lint:drupal/parameter-blank-line
  public function spaces(
    int $a,
    
    int $b,
  ): void {
  }

  /**
   * A blank line in an abstract method.
   */
  // @mago-expect lint:drupal/parameter-blank-line
  abstract public function abstractMethod(
    int $a,

    int $b,
  ): void;

  /**
   * A blank line in the promoted parameters of a constructor.
   */
  // @mago-expect lint:drupal/parameter-blank-line
  public function __construct(
    public readonly int $a,

    public readonly int $b = 0,
  ) {
  }

  /**
   * A blank line in a static method with a return type.
   */
  // @mago-expect lint:drupal/parameter-blank-line
  public static function staticMethod(
    int $a,

    int $b,
  ): ?int {
    return $a + $b;
  }

  /**
   * A blank line after a trailing comment, and after and before a comment line.
   */
  // @mago-expect lint:drupal/post-statement-comment
  // @mago-expect lint:drupal/inline-comment-blank-line
  // @mago-expect lint:drupal/parameter-blank-line(3)
  public function comments(
    int $a, // The first.

    // The second.

    int $b,
    // The third.
    int $c,

    // The fourth.
  ): void {
  }

  /**
   * A blank line inside an attribute is not in the list.
   */
  public function attribute(
    #[ParameterBlankLineMarker('one')]
    int $a,
    #[ParameterBlankLineMarker(
      'two',

    )]
    int $b,
  ): void {
  }

  /**
   * A blank line in a default value is not in the list.
   *
   * @param array<int> $array
   *   An array.
   * @param array<int> $long
   *   An array.
   * @param object $object
   *   An object.
   * @param int $sum
   *   A sum.
   * @param string $text
   *   A string.
   */
  public function defaults(
    array $array = [
      1,

      2,
    ],
    array $long = array(
      1,

      2,
    ),
    object $object = new \stdClass(

    ),
    string $text = <<<'TEXT'
      One

      Two
      TEXT,
    int $sum = (
      1 +

      2
    ),
  ): void {
  }

  /**
   * A blank line in a comment in the list.
   */
  public function commentBlock(
    int $a,
    /*
     * One.
     *
     * Two.
     */
    int $b,
    // @mago-expect lint:drupal/inline-comment
    // @mago-expect lint:drupal/doc-comment
    /**

     * Three.
     */
    int $c,
  ): void {
  }

  /**
   * A blank line in the body, or between the parenthesis and the brace.
   */
  public function body(
    int $a,
  ): void

  {

    $a = $a + 1;

    echo $a;

  }

  /**
   * A parameter list on one line.
   */
  public function oneLine(int $a, int $b): void {
  }

  /**
   * Closures are checked too.
   *
   * @return array<callable>
   *   The closures.
   */
  public function closures(): array {
    // @mago-expect lint:drupal/parameter-blank-line
    $parameters = function (
      int $a,

      int $b,
    ): void {
    };
    $nested = function (int $a) {
      // @mago-expect lint:drupal/parameter-blank-line
      return function (
        int $b,

        int $c,
      ) use ($a): int {
        return $a + $b + $c;
      };
    };
    $arrow = fn (
      int $a,

      int $b,
    ): int => $a + $b;

    return [$parameters, $nested, $arrow];
  }

  /**
   * The use list is checked when the parameter list spans lines.
   *
   * @return array<callable>
   *   The closures.
   */
  public function useLists(int $x, int $y): array {
    // @mago-expect lint:drupal/parameter-blank-line
    $inUse = function (
      int $a,
    ) use (
      $x,

      $y,
    ): int {
      return $a + $x + $y;
    };
    // @mago-expect lint:drupal/parameter-blank-line
    $beforeUse = function (
      int $a,
    )

    use ($x): int {
      return $a + $x;
    };
    $oneLineParameters = function (int $a) use (
      $x,

      $y,
    ): int {
      return $a + $x + $y;
    };

    return [$inUse, $beforeUse, $oneLineParameters];
  }

  /**
   * A method of an anonymous class is checked on its own.
   */
  public function anonymousClass(): object {
    return new class() {

      /**
       * A blank line between two parameters.
       */
      // @mago-expect lint:drupal/parameter-blank-line
      public function run(
        int $a,

        int $b,
      ): void {
      }

    };
  }
  // @mago-format-ignore-end

}

// @mago-format-ignore-start
/**
 * A blank line in a function.
 */
// @mago-expect lint:drupal/parameter-blank-line
function parameter_blank_line_function(
  int $a,

  int $b,
): void {
}

/**
 * A blank line in a function that returns a reference.
 */
// @mago-expect lint:drupal/parameter-blank-line
function &parameter_blank_line_reference(
  array &$a,

  int $b,
): array {
  return $a;
}

// @mago-format-ignore-end
