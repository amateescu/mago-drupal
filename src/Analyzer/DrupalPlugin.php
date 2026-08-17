<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer;

use Mago\Sdk\Analyzer\Plugin;
use Mago\Sdk\Analyzer\PluginDefinition;
use Mago\Sdk\Analyzer\PluginRegistry;

/**
 * Teaches the analyzer about Drupal's runtime wiring.
 *
 * @internal
 */
final class DrupalPlugin implements Plugin
{
    public function __construct(
        private readonly bool $core = false,
    ) {}

    public function getDefinition(): PluginDefinition
    {
        return new PluginDefinition(
            identifier: 'drupal',
            name: 'Drupal',
            description: 'Resolves Drupal services, entity storage, plugins and configuration.',
        );
    }

    public function register(PluginRegistry $registry): void
    {
        $registry->enableProviderMemoization();

        // @todo Register the container, entity storage, plugin manager and
        //   config return-type providers once the service and plugin index
        //   lands. All of them need the same YAML-derived index, so it comes
        //   first.
        // @todo Register a property initialization provider for
        //   ContainerInjectionInterface::create() and #[Autowire].
        // @todo Register routing, hook and event-subscriber entry points so
        //   find-unused-definitions stops reporting framework-invoked code.
    }

    /**
     * Whether rules that only apply to Drupal core itself are enabled.
     */
    public function isCore(): bool
    {
        return $this->core;
    }
}
