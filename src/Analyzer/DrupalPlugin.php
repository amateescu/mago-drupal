<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer;

use Mago\Sdk\Analyzer\Plugin;
use Mago\Sdk\Analyzer\PluginDefinition;
use Mago\Sdk\Analyzer\PluginRegistry;

/**
 * Gives the analyzer the facts about Drupal's runtime wiring.
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

        // @todo Register the return-type providers for the container, the
        //   entity storage, the plugin manager and the config after the
        //   service and plugin index exists. All of them must have the same
        //   YAML-derived index, so build that index first.
        // @todo Register a property initialization provider for
        //   ContainerInjectionInterface::create() and #[Autowire].
        // @todo Register the routing, hook and event-subscriber entry points,
        //   so that find-unused-definitions stops its reports on code that
        //   the framework calls.
    }

    /**
     * Whether the rules that apply only to Drupal core are enabled.
     */
    public function isCore(): bool
    {
        return $this->core;
    }
}
