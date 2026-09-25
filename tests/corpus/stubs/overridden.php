<?php

/**
 * @file
 * Core declarations that the shipped stub files sharpen.
 *
 * These stand in for the real core classes, so the fixtures prove both halves
 * of the merge: a member the stub names wins, a member it leaves out keeps
 * what core declares.
 */

declare(strict_types=1);

namespace Drupal\Core\Security {
    interface TrustedCallbackInterface {}
}

namespace Drupal\Core {
    class GeneratedUrl
    {
        public function onlyOnGeneratedUrl(): void {}
    }

    class Url implements \Drupal\Core\Security\TrustedCallbackInterface
    {
        /**
         * @return string|GeneratedUrl
         */
        public function toString($collect_bubbleable_metadata = false)
        {
            return '';
        }

        public function getRouteName(): string
        {
            return '';
        }
    }
}

namespace Drupal\Core\Cache {
    interface CacheBackendInterface
    {
        public const CACHE_PERMANENT = -1;

        public function get($cid, $allow_invalid = false);

        /**
         * @param array $cids
         * @return array
         */
        public function getMultiple(&$cids, $allow_invalid = false);

        /**
         * @param array $items
         */
        public function setMultiple(array $items);

        /**
         * @param array $cids
         */
        public function deleteMultiple(array $cids);

        public function deleteAll();

        /**
         * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use
         *   CacheBackendInterface::deleteAll() or cache tag invalidation instead.
         */
        public function invalidateAll();
    }
}

namespace PHPUnit\Framework\Attributes {
    #[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
    final class IgnoreDeprecations {}
}

namespace Drupal\Component\Utility {
    class DeprecationHelper
    {
        /**
         * @param callable(): mixed $currentCallable
         * @param callable(): mixed $deprecatedCallable
         */
        public static function backwardsCompatibleCall(
            string $currentVersion,
            string $deprecatedVersion,
            callable $currentCallable,
            callable $deprecatedCallable,
        ): mixed {
            return null;
        }
    }
}

namespace Drupal\corpus\Legacy {
    /**
     * @deprecated in corpus:1.0.0 and is removed from corpus:2.0.0. Use
     *   something else instead.
     */
    class RetiredThing
    {
        public function stillHere(): void {}
    }
}
