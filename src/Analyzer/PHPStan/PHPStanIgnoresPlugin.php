<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\PHPStan;

use Mago\Sdk\Analyzer\Plugin;
use Mago\Sdk\Analyzer\PluginDefinition;
use Mago\Sdk\Analyzer\PluginRegistry;

/**
 * Lets `@phpstan-ignore` comments silence the matching Mago issues.
 *
 * Off by default: a project that runs PHPStan next to Mago, or moves from one
 * to the other, turns it on to avoid writing a `@mago-expect` for every line
 * PHPStan already ignores. Nothing here depends on Drupal.
 *
 * @internal
 */
final class PHPStanIgnoresPlugin implements Plugin
{
    public function getDefinition(): PluginDefinition
    {
        return new PluginDefinition(
            identifier: 'phpstan-ignores',
            name: 'PHPStan ignores',
            description: 'Drops the issues a @phpstan-ignore comment ignores for PHPStan.',
            defaultEnabled: false,
        );
    }

    public function register(PluginRegistry $registry): void
    {
        $registry->registerIssueFilterHook(new PHPStanIgnoreFilter());
    }
}
