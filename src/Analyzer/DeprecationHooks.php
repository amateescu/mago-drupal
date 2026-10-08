<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer;

use amateescu\MagoDrupal\Analyzer\Hooks\DeprecatedClassReferenceHook;
use amateescu\MagoDrupal\Analyzer\Hooks\DeprecatedConstantHook;
use amateescu\MagoDrupal\Analyzer\Hooks\DeprecatedInterfaceHook;
use amateescu\MagoDrupal\Analyzer\Hooks\DeprecatedOriginalHook;
use amateescu\MagoDrupal\Analyzer\Hooks\DeprecatedOverrideHook;
use amateescu\MagoDrupal\Analyzer\Hooks\DeprecatedPropertyHook;
use amateescu\MagoDrupal\Analyzer\Hooks\DeprecatedUse;
use amateescu\MagoDrupal\Analyzer\Hooks\DeprecationScopeFilter;
use amateescu\MagoDrupal\Analyzer\Hooks\DeprecationTargetFilter;
use amateescu\MagoDrupal\Internal\DeprecationTarget;
use amateescu\MagoDrupal\Internal\Indexes;
use Mago\Sdk\Analyzer\PluginRegistry;

/**
 * Registers the deprecation scope and target filters and the deprecation
 * checks Mago has none of.
 *
 * @internal
 */
final class DeprecationHooks
{
    private function __construct() {}

    /**
     * Registers the filters, `deprecated-original`, and the checks built on
     * the `@deprecated` symbols under the root. The symbols are read off
     * disk at registration, since the interface and method checks want their
     * targets before the first request; a root without any needs none.
     */
    public static function register(PluginRegistry $registry, Indexes $indexes, DeprecationTarget $deprecations): void
    {
        $registry->registerIssueFilterHook(new DeprecationScopeFilter());
        if (!$deprecations->isAll()) {
            $registry->registerIssueFilterHook(new DeprecationTargetFilter($deprecations));
        }

        if ($deprecations->keeps(DeprecatedOriginalHook::MESSAGE)) {
            $registry->registerNodeAnalysisHook(new DeprecatedOriginalHook());
        }

        $symbols = $indexes->deprecatedSymbols();
        if ($symbols->isEmpty()) {
            return;
        }

        $use = new DeprecatedUse($deprecations);
        $registry->registerNodeAnalysisHook(new DeprecatedConstantHook($symbols, $use));
        $registry->registerNodeAnalysisHook(new DeprecatedPropertyHook($symbols, $use));
        $registry->registerNodeAnalysisHook(new DeprecatedClassReferenceHook($symbols, $use));
        $interfaces = DeprecatedInterfaceHook::of($symbols, $use);
        if ($interfaces !== null) {
            $registry->registerClassLikeAnalysisHook($interfaces);
        }

        $overrides = DeprecatedOverrideHook::of($symbols, $use);
        if ($overrides !== null) {
            $registry->registerMethodCallAnalysisHook($overrides);
        }
    }
}
