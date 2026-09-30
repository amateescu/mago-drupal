<?php

/**
 * @file
 * Service registrations written in PHP, picked up by the codebase scan.
 */

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\corpus\Nested\Other;
use Drupal\corpus\Nested\Thing;
use Symfony\Component\DependencyInjection\ChildDefinition;
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
    $container->setAlias('corpus.provided_alias', 'corpus.provided');
    // Computed at runtime, so the index cannot know it.
    $container->register('corpus.provided_dynamic', $this->pickClass());
    $this->registerDefinitions($container);
  }

  /**
   * Registers definitions, which are private unless they say otherwise.
   */
  private function registerDefinitions(ContainerBuilder $container): void {
    $container->setDefinition('corpus.defined', new Definition(Thing::class));
    $container->setDefinition(
      'corpus.defined_public',
      (new Definition(Thing::class))
        ->addTag('corpus')
        ->setPublic(TRUE),
    );
    $container->setDefinition('corpus.defined_result', new Definition(Thing::class))->setPublic(TRUE);
    $container->register('corpus.registered_private', Thing::class)->setPublic(FALSE);
    // A child takes its visibility and class from its parent.
    $container->setDefinition('corpus.defined_child', new ChildDefinition('corpus.thing'));
    // Built somewhere else, so it may be public.
    $container->setDefinition('corpus.defined_elsewhere', $this->buildDefinition());

    $public = new Definition(Thing::class);
    $public->addTag('corpus');
    $public->setPublic(TRUE);
    $container->setDefinition('corpus.defined_variable', $public);

    $private = new Definition();
    $private->setClass(Thing::class);
    $container->setDefinition('corpus.defined_private_variable', $private);

    // Another method may make it public.
    $configured = new Definition(Thing::class);
    $this->configure($configured);
    $container->setDefinition('corpus.defined_configured', $configured);

    // The scan does not follow the flow, so a variable that holds two
    // definitions is public when either is, and has neither class.
    $reused = new Definition(Thing::class);
    $reused->setPublic(TRUE);
    $container->setDefinition('corpus.reused_first', $reused);
    $reused = (new ChildDefinition('corpus.thing'))->setClass(Other::class);
    $container->setDefinition('corpus.reused_second', $reused);

    // The definition setDefinition() returns may be made public later.
    $assigned = $container->setDefinition('corpus.defined_assigned', new Definition(Thing::class));
    $assigned->setPublic(TRUE);
    // alter() makes it public.
    $container->setDefinition('corpus.defined_altered', new Definition(Thing::class));
    $container->setAlias('corpus.provided_private_alias', 'corpus.provided')->setPublic(FALSE);
    // A computed flag may be TRUE.
    $container->register('corpus.computed_visibility', Thing::class)->setPublic($this->visible());
  }

  /**
   * Changes definitions after every provider registered its own.
   */
  public function alter(ContainerBuilder $container): void {
    $container->getDefinition('corpus.defined_altered')->setPublic(TRUE);
    // What alter() registers is not indexed, so the class register() gave
    // stays.
    $container->register('corpus.provided', Other::class);
  }

  /**
   * Stands in for a visibility decided at runtime.
   */
  private function visible(): bool {
    return TRUE;
  }

  /**
   * Stands in for a helper that finishes a definition.
   */
  private function configure(Definition $definition): void {
    $definition->setPublic(TRUE);
  }

  /**
   * Stands in for a class chosen at runtime.
   */
  private function pickClass(): string {
    return Thing::class;
  }

  /**
   * Stands in for a definition built by another method.
   */
  private function buildDefinition(): Definition {
    return new Definition(Thing::class);
  }

}
