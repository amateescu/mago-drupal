<?php

/**
 * @file
 * Scopes where a deprecated call is the point of the code.
 */

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\corpus\Entity\CorpusThing;
use Drupal\corpus\Legacy\RetiredThing;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;

/**
 * A class covering deprecated behaviour on purpose.
 *
 * @group legacy
 */
final class LegacyDeprecations {

  /**
   * The whole class is in scope, so nothing here is reported.
   */
  public function anywhere(): void {
    (new RetiredThing())->stillHere();
  }

}

/**
 * One method at a time.
 */
final class Deprecations {

  /**
   * A legacy group on the method covers only this body.
   *
   * @group legacy
   */
  public function grouped(): void {
    (new RetiredThing())->stillHere();
  }

  /**
   * The PHPUnit attribute does the same.
   */
  #[IgnoreDeprecations]
  public function attributed(): void {
    (new RetiredThing())->stillHere();
  }

  /**
   * The helper runs the deprecated branch on older core.
   */
  public function wrapped(): void {
    DeprecationHelper::backwardsCompatibleCall(
      currentVersion: '11.4.0',
      deprecatedVersion: '11.2.0',
      currentCallable: static fn (): mixed => NULL,
      deprecatedCallable: static fn (): mixed => (new RetiredThing())->stillHere(),
    );
  }

  /**
   * The same goes for the magic `original` property.
   */
  public function wrappedOriginal(CorpusThing $thing): void {
    DeprecationHelper::backwardsCompatibleCall(
      currentVersion: '11.4.0',
      deprecatedVersion: '11.2.0',
      currentCallable: static fn (): mixed => NULL,
      deprecatedCallable: static fn (): mixed => $thing->original,
    );
  }

  /**
   * Deprecated code may call deprecated code.
   *
   * @deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use
   *   plain() instead.
   *
   * @see https://www.drupal.org/node/1234567
   */
  public function retired(): void {
    (new RetiredThing())->stillHere();
  }

  /**
   * An unmarked method next to them is still reported.
   */
  public function plain(): void {
    // @mago-expect analysis:deprecated-class
    (new RetiredThing())->stillHere();
  }

}

/**
 * A deprecated class may use deprecated code anywhere in its body.
 *
 * @deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use
 *   \Drupal\corpus\Deprecations instead.
 *
 * @see https://www.drupal.org/node/1234567
 */
final class RetiredDeprecations {

  /**
   * Neither the deprecated class nor the original property is reported.
   */
  public function anywhere(CorpusThing $thing): void {
    (new RetiredThing())->stillHere();
    $thing->original?->id();
  }

}

/**
 * Forwards the deprecated method it implements.
 *
 * PHPStan counts the override as deprecated too, so the call is not reported.
 */
abstract class ForwardingBackend implements CacheBackendInterface {

  /**
   * Keeps the decorated backend.
   */
  public function __construct(
    protected CacheBackendInterface $inner,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function invalidateAll() {
    $this->inner->invalidateAll();
  }

}

/**
 * Forwards the deprecated method for the backends that use it.
 */
trait ForwardingTrait {

  /**
   * {@inheritdoc}
   */
  public function invalidateAll() {
    $this->backend()->invalidateAll();
  }

  /**
   * The decorated backend.
   */
  abstract protected function backend(): CacheBackendInterface;

}

/**
 * Gets the forward from the trait, and inherits the deprecated declaration.
 */
abstract class TraitForwardingBackend implements CacheBackendInterface {

  use ForwardingTrait;

}

/**
 * Keeps the method although the interface deprecates it.
 */
abstract class KeptBackend implements CacheBackendInterface {

  /**
   * Invalidates everything, and stays when the interface drops the method.
   *
   * @not-deprecated
   */
  public function invalidateAll() {
    // @mago-expect analysis:deprecated-method
    $this->inner()->invalidateAll();
  }

  /**
   * The decorated backend.
   */
  abstract protected function inner(): CacheBackendInterface;

}

/**
 * Forwards the deprecated method for a class that does not inherit it.
 */
trait LooseForwardingTrait {

  /**
   * Invalidates everything in the backend.
   */
  public function invalidateAll(): void {
    // @mago-expect analysis:deprecated-method
    $this->backend()->invalidateAll();
  }

  /**
   * The backend to forward to.
   */
  abstract protected function backend(): CacheBackendInterface;

}

/**
 * Uses the trait without implementing the cache backend interface.
 */
final class LooseForwarder {

  use LooseForwardingTrait;

  /**
   * Keeps the backend.
   */
  public function __construct(
    private readonly CacheBackendInterface $cache,
  ) {}

  /**
   * {@inheritdoc}
   */
  protected function backend(): CacheBackendInterface {
    return $this->cache;
  }

}
