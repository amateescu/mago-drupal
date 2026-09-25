<?php

/**
 * @file
 * Call-site checks: cacheable dependencies, includes and logger channels.
 */

declare(strict_types=1);

namespace Drupal\corpus\Audit;

use Drupal\Core\Cache\CacheableMetadataRefinable;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\Context\ContextInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\corpus\Nested\Cacheable;
use Drupal\corpus\Nested\SealedThing;
use Drupal\corpus\Nested\Thing;

/**
 * A service using the trait, so its logger has to come in by id.
 */
final class Calls {

  use DependencySerializationTrait;

  /**
   * Assigned from the factory, which the trait cannot restore.
   */
  protected LoggerChannelInterface $logger;

  /**
   * Injected by service id, which is fine.
   */
  protected LoggerChannelInterface $channel;

  /**
   * The factory itself, kept for later.
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * Fetched through the kept factory, which the trait cannot restore either.
   */
  protected LoggerChannelInterface $later;

  public function __construct(
    LoggerChannelFactoryInterface $factory,
    LoggerChannelInterface $channel,
    protected readonly ModuleHandlerInterface $moduleHandler,
  ) {
    // @mago-expect analysis:drupal/logger-from-factory
    $this->logger = $factory->get('corpus');
    $this->channel = $channel;
    $this->loggerFactory = $factory;
    // @mago-expect analysis:drupal/logger-from-factory
    $this->later = $this->loggerFactory->get('corpus');
  }

  /**
   * Only objects carrying metadata may be added as dependencies.
   */
  public function cacheability(
    CacheableMetadataRefinable $metadata,
    ContextInterface $context,
    RendererInterface $renderer,
    Thing $thing,
    SealedThing $sealed,
    Cacheable $cacheable,
    mixed $unknown,
  ): void {
    $metadata->addCacheableDependency($cacheable);
    // `mixed` might still be a cacheable dependency.
    $metadata->addCacheableDependency($unknown);
    // A subclass of an open class may carry cacheability.
    $metadata->addCacheableDependency($thing);
    // @mago-expect analysis:drupal/cacheable-dependency
    $metadata->addCacheableDependency($sealed);
    // @mago-expect analysis:drupal/cacheable-dependency
    $metadata->addCacheableDependency('a string');
    // A context names the parameter `$dependency`.
    // @mago-expect analysis:drupal/cacheable-dependency
    $context->addCacheableDependency(dependency: 'a string');

    // The renderer takes the render array first.
    $build = [];
    $renderer->addCacheableDependency($build, $cacheable);
    // @mago-expect analysis:drupal/cacheable-dependency
    $renderer->addCacheableDependency($build, 42);

    // Named arguments fill their parameter wherever they are written, so
    // the render array is never the dependency.
    $renderer->addCacheableDependency(dependency: $cacheable, elements: $build);
    // @mago-expect analysis:drupal/cacheable-dependency
    $renderer->addCacheableDependency(dependency: 42, elements: $build);
  }

  /**
   * Includes are checked against the module directory.
   */
  public function includes(string $name): void {
    $this->moduleHandler->loadInclude('corpus', 'inc', 'corpus.pages');
    $this->moduleHandler->loadInclude('corpus', 'inc', $name);
    // @mago-expect analysis:drupal/load-include
    $this->moduleHandler->loadInclude('corpus', 'inc', 'corpus.missing');
    // @mago-expect analysis:drupal/load-include
    $this->moduleHandler->loadInclude('corpus', 'install');
    // @mago-expect analysis:drupal/load-include
    $this->moduleHandler->loadInclude('nowhere', 'inc');

    // The type is written first here, and it is not a module name.
    $this->moduleHandler->loadInclude(type: 'inc', module: 'corpus', name: 'corpus.pages');
    // @mago-expect analysis:drupal/load-include
    $this->moduleHandler->loadInclude(type: 'inc', module: 'corpus', name: 'corpus.missing');
  }

  /**
   * Reads the loggers so neither is write-only.
   *
   * @return list<LoggerChannelInterface>
   *   Both channels.
   */
  public function loggers(): array {
    return [$this->logger, $this->channel, $this->later, $this->loggerFactory->get('other')];
  }

}
