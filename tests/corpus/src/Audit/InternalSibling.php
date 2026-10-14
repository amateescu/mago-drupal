<?php

/**
 * @file
 * A module's tests may extend the module's own internal classes.
 */

declare(strict_types=1);

namespace Drupal\Tests\corpus_dep\Unit;

use Drupal\corpus_dep\InternalBase;

/**
 * Owned by corpus_dep through the test namespace, so not reported.
 */
final class InternalSibling extends InternalBase {}

/**
 * Builds an anonymous class on its own module's internal base.
 */
final class AnonymousSibling {

  /**
   * The module owns the base, so nothing is reported.
   */
  public function build(): InternalBase {
    return new class extends InternalBase {};
  }

}
