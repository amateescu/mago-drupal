<?php

/**
 * @file
 * Signatures the shipped stub files sharpen.
 */

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Url;

/**
 * Exercises the stub files loaded by the initialization hook.
 */
final class Stubs {

  /**
   * The argument decides which branch of the conditional return applies.
   */
  public function urls(Url $url): void {
    $this->wantsGeneratedUrl($url->toString(TRUE));
    $this->wantsString($url->toString());
    // @mago-expect analysis:invalid-argument
    $this->wantsGeneratedUrl($url->toString());
  }

  /**
   * A member the stub leaves out keeps the declaration core ships.
   */
  public function merged(Url $url, CacheBackendInterface $cache): void {
    $this->wantsString($url->getRouteName());
    $this->wantsTrustedCallback($url);
    $this->wantsInt(CacheBackendInterface::CACHE_PERMANENT);
    $cache->deleteAll();
  }

  /**
   * A cache item is a shape, not a bare object.
   *
   * The database backend returns the timestamps as strings and adds
   * properties of its own.
   */
  public function cacheItem(CacheBackendInterface $cache): void {
    $item = $cache->get('corpus');
    if ($item === FALSE) {
      return;
    }

    $this->wantsString($item->cid);
    // The database backend returns `expire` as a numeric string.
    // @mago-expect analysis:possibly-invalid-argument
    $this->wantsInt($item->expire);
    // A backend's own property can be read, but the shape does not type it.
    // @mago-expect analysis:ambiguous-object-property-access
    $this->wantsMixed($item->checksum);
  }

  /**
   * A decorator passes plain arrays on, the way core documents them.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param array $cids
   *   The cache IDs.
   * @param array $items
   *   The cache items.
   */
  public function passThrough(CacheBackendInterface $cache, array $cids, array $items): void {
    $cache->setMultiple($items);
    $cache->deleteMultiple($cids);
    foreach ($cache->getMultiple($cids) as $cid => $item) {
      // A numeric cache ID comes back as an integer key.
      // @mago-expect analysis:less-specific-argument
      $this->wantsString($cid);
    }
    // @mago-expect analysis:deprecated-method
    $cache->invalidateAll();
  }

  /**
   * Takes a generated URL, so a plain string shows up as an argument error.
   */
  private function wantsGeneratedUrl(GeneratedUrl $url): void {
    $url->onlyOnGeneratedUrl();
  }

  /**
   * Takes anything, for a property the shape leaves open.
   */
  private function wantsMixed(mixed $value): void {
  }

  /**
   * Takes a trusted callback, proving the stub keeps the interface.
   */
  private function wantsTrustedCallback(TrustedCallbackInterface $callback): void {
  }

  /**
   * Takes a string, so a wrong type shows up as an argument error.
   */
  private function wantsString(string $value): void {
  }

  /**
   * Takes an int, so a wrong type shows up as an argument error.
   */
  private function wantsInt(int $value): void {
  }

}
