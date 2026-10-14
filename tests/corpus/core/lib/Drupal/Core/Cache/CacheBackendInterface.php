<?php

/**
 * @file
 * Core's cache backend interface, where core keeps it, so the extension reads
 * its deprecations off disk the way it does on a site.
 */

namespace Drupal\Core\Cache;

interface CacheBackendInterface
{
    public const CACHE_PERMANENT = -1;

    public function get($cid, $allow_invalid = false);

    /**
     * @param array $cids
     * @return array
     */
    public function getMultiple(&$cids, $allow_invalid = false);

    /**
     * @param array $items
     */
    public function setMultiple(array $items);

    /**
     * @param array $cids
     */
    public function deleteMultiple(array $cids);

    public function deleteAll();

    /**
     * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use
     *   CacheBackendInterface::deleteAll() or cache tag invalidation instead.
     */
    public function invalidateAll();
}
