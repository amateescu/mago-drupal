<?php

/**
 * @file
 * Classes the fixtures use, declared the way their owners do: the URL classes
 * the analyzer types by argument, PHPUnit's attribute and the helper that
 * scope deprecations, and a deprecated class.
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
