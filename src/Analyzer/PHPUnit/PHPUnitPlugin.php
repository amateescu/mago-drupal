<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\PHPUnit;

use Mago\Sdk\Analyzer\Plugin;
use Mago\Sdk\Analyzer\PluginDefinition;
use Mago\Sdk\Analyzer\PluginRegistry;

/**
 * Gives the analyzer the facts PHPUnit and Prophecy leave out of their types.
 *
 * Nothing here depends on Drupal: the providers read PHPUnit's and Prophecy's
 * own classes, so they apply to any test suite.
 *
 * @internal
 */
final class PHPUnitPlugin implements Plugin
{
    public function getDefinition(): PluginDefinition
    {
        return new PluginDefinition(
            identifier: 'phpunit',
            name: 'PHPUnit',
            description: 'Narrows emptiness assertions, types mock unions and Prophecy calls, and reports mocks and prophecies documented with the wrong type.',
        );
    }

    public function register(PluginRegistry $registry): void
    {
        $registry->enableProviderMemoization();
        $registry->registerMethodAssertionProvider(new AssertionProvider());
        $registry->registerMethodReturnTypeProvider(new MockUnionProvider());
        $registry->registerMethodCallAnalysisHook(new PlainMockCallHook('expects'));
        $registry->registerMethodCallAnalysisHook(new PlainMockCallHook('method'));
        $registry->registerMethodReturnTypeProvider(new ProphecyCallProvider());
        $registry->registerNodeAnalysisHook(new ProphecyUnionHook());
        $thisOut = new ProphecyThisOutProvider();
        $registry->registerMethodReturnTypeProvider($thisOut);
        $registry->registerMethodAssertionProvider($thisOut);
    }
}
