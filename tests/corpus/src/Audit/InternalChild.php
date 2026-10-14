<?php

/**
 * @file
 * Classes extending another module's internal class.
 */

declare(strict_types=1);

namespace Drupal\corpus\Audit;

use Drupal\corpus_dep\InternalBase;
use Drupal\corpus_dep\PublicBase;

/**
 * Extends an internal class of another module.
 */
// @mago-expect analysis:drupal/internal-class-extension
final class InternalChild extends InternalBase {}

/**
 * Extends an internal class of another module.
 */
// @mago-expect analysis:drupal/internal-class-extension
abstract class InternalChildBase extends InternalBase {}

/**
 * Only the grandparent is internal, so nothing is reported here.
 */
abstract class InternalGrandchildBase extends InternalChildBase {}

/**
 * Extends a public class of another module.
 */
final class PublicChild extends PublicBase {}

/**
 * Builds anonymous classes on internal and public bases.
 */
final class AnonymousChildren {

  /**
   * Only the anonymous class on the internal base is reported.
   */
  public function build(): array {
    return [
      // @mago-expect analysis:drupal/internal-class-extension
      new class extends InternalBase {},
      new class extends PublicBase {},
    ];
  }

}
