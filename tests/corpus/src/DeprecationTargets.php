<?php

/**
 * @file
 * Deprecations the corpus worker's `--deprecations=12` keeps and drops.
 */

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\corpus\Legacy\ChildSchedule;
use Drupal\corpus\Legacy\LateThing;
use Drupal\corpus\Legacy\LateTrait;
use Drupal\corpus\Legacy\Schedule;

/**
 * Uses symbols that Drupal removes in 12 and in 13.
 */
final class DeprecationTargets {

  use LateTrait;

  /**
   * Only the calls whose target Drupal 12 removes are reported.
   */
  public function calls(): void {
    // @mago-expect analysis:deprecated-function
    corpus_removed_in_twelve();
    corpus_removed_in_thirteen();
    // @mago-expect analysis:deprecated-method
    (new Schedule())->soon();
    (new Schedule())->later();
    // The declaration sits in the parent class.
    (new ChildSchedule())->later();
    new LateThing();
  }

  /**
   * A namespaced name falls back to the global constant.
   */
  public function constants(): int {
    // @mago-expect analysis:deprecated-constant
    return CORPUS_REMOVED_IN_TWELVE + CORPUS_REMOVED_IN_THIRTEEN;
  }

}
