<?php

/**
 * @file
 * Deprecated symbols that Drupal removes in 12 and in 13.
 *
 * The corpus worker runs with `--deprecations=12`, so only the first kind is
 * reported. src/DeprecationTargets.php uses both.
 */

declare(strict_types=1);

namespace {
    /**
     * @deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use
     *   corpus_new() instead.
     */
    function corpus_removed_in_twelve(): void {}

    /**
     * @deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use
     *   corpus_new() instead.
     */
    function corpus_removed_in_thirteen(): void {}

    /**
     * @deprecated in drupal:11.3.0 and is removed from drupal:12.0.0.
     */
    const CORPUS_REMOVED_IN_TWELVE = 1;

    /**
     * @deprecated in drupal:11.4.0 and is removed from drupal:13.0.0.
     */
    const CORPUS_REMOVED_IN_THIRTEEN = 2;
}

namespace Drupal\corpus\Legacy {
    class Schedule
    {
        /**
         * @deprecated in drupal:11.3.0 and is removed from drupal:12.0.0.
         */
        public function soon(): void {}

        /**
         * @deprecated in drupal:11.4.0 and is removed from drupal:13.0.0.
         */
        public function later(): void {}
    }

    class ChildSchedule extends Schedule {}

    /**
     * @deprecated in drupal:11.4.0 and is removed from drupal:13.0.0.
     */
    class LateThing {}

    /**
     * @deprecated in drupal:11.4.0 and is removed from drupal:13.0.0.
     */
    trait LateTrait {}
}
