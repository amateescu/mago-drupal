<?php

/**
 * @file
 * Service registrations written in PHP, picked up by the codebase scan.
 */

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\corpus\Nested\Thing;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Registers services the way core and contrib providers do.
 */
final class CorpusServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    $container->register('corpus.provided', Thing::class);
    $container->register('corpus.provided_string', 'Drupal\corpus\Nested\Thing')->addTag('event_subscriber');
    $container->register('corpus.chained')->setClass(Thing::class);
    $container->setDefinition('corpus.defined', new Definition(Thing::class));
    $container->setAlias('corpus.provided_alias', 'corpus.provided');
    // Computed at runtime, so the index cannot know it.
    $container->register('corpus.provided_dynamic', $this->pickClass());
  }

  /**
   * Stands in for a class chosen at runtime.
   */
  private function pickClass(): string {
    return Thing::class;
  }

}
