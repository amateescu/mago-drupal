<?php

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

}
