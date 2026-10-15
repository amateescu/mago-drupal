<?php

declare(strict_types=1);

namespace Drupal\corpus;

/**
 * Declares parameters with a null default in each shape the rule tells apart.
 */
final class ExplicitNullableParam {

  /**
   * One type, by value and by reference.
   */
  // @mago-expect lint:drupal/explicit-nullable-param(2)
  public function single(int $id = NULL, array &$cache = NULL): void {
  }

  /**
   * A union and an intersection.
   */
  // @mago-expect lint:drupal/explicit-nullable-param(2)
  public function compound(int|string $id = NULL, \Countable&\Traversable $items = NULL): void {
  }

  /**
   * A default written as a qualified constant.
   */
  // @mago-expect lint:drupal/explicit-nullable-param(1)
  public function qualified(string $name = \NULL): void {
  }

  /**
   * A closure and an arrow function.
   */
  public function closures(): array {
    // @mago-expect lint:drupal/explicit-nullable-param(2)
    return [
      function (string $name = NULL): ?string {
        return $name;
      },
      fn (self $parent = NULL): ?self => $parent,
    ];
  }

  /**
   * Types that allow null, no type, and other defaults.
   */
  public function fine(
    ?int $a = NULL,
    int|string|null $b = NULL,
    mixed $c = NULL,
    $d = NULL,
    int $e = 0,
    ?string $f = 'x',
  ): void {
  }

}
