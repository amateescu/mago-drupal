<?php

// @mago-expect lint:drupal/file-comment
/**
 * @file
 * Docblock types named by imports that the code uses, and by one it does not.
 */

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\Core\State\StateInterface;
use Drupal\corpus\Nested\Thing;

/**
 * Names its parameter types by their short names.
 */
final class DocTypeNamespaceCases {

  /**
   * The code names StateInterface too, so its short name is fine.
   *
   * @param StateInterface $state
   *   The state.
   */
  public function usedInCode(StateInterface $state): void {
  }

  // @mago-expect lint:drupal/doc-type-namespace
  /**
   * Only docblocks name Thing, so Coder 9 reports its import as unused.
   *
   * @param Thing $thing
   *   The thing.
   */
  public function docblockOnly($thing): void {
  }

  // @mago-expect lint:drupal/doc-type-namespace
  /**
   * Names Thing as a generic argument, which Coder 9 does not read either.
   *
   * @return array<string, Thing>
   *   The things, keyed by name.
   */
  public function genericArgument(): array {
    return [];
  }

  // @mago-expect lint:drupal/doc-type-namespace
  /**
   * Names Thing as the argument of a list.
   *
   * @param list<Thing> $things
   *   The things.
   */
  public function listArgument($things): void {
  }

  // @mago-expect lint:drupal/doc-type-namespace
  /**
   * Names Thing as an array shape value.
   *
   * @param array{thing: Thing, count: int} $item
   *   The item.
   */
  public function shapeValue($item): void {
  }

  // @mago-expect lint:drupal/doc-type-namespace
  /**
   * Names Thing inside a generic inside a generic.
   *
   * @return array<string, list<Thing>>
   *   The things, grouped by name.
   */
  public function nestedGeneric(): array {
    return [];
  }

  // @mago-expect lint:drupal/doc-type-namespace
  /**
   * Names Thing as a nullable generic argument.
   *
   * @param list<?Thing> $things
   *   The things, with gaps.
   */
  public function nullableArgument($things): void {
  }

  /**
   * Has only built-in types inside the generic.
   *
   * @return array<string, list<int>>
   *   The counts, grouped by name.
   */
  public function builtInArgument(): array {
    return [];
  }

  /**
   * Uses Thing as an array shape key, which is not a type.
   *
   * @param array{Thing: int} $counts
   *   The counts.
   */
  public function shapeKey($counts): void {
  }

  /**
   * Names a class of this namespace, which has no import.
   *
   * @return list<DocTypeNamespaceCases>
   *   The cases.
   */
  public function notImported(): array {
    return [];
  }

  /**
   * Writes the full name of Thing inside the generic.
   *
   * @return list<\Drupal\corpus\Nested\Thing>
   *   The things.
   */
  public function fullyQualifiedArgument(): array {
    return [];
  }

}
