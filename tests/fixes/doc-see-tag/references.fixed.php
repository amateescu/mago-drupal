<?php

namespace Drupal\corpus;

/**
 * Handles access to nodes.
 *
 * @see \Drupal\node\NodeAccessControlHandler.
 * @see https://www.drupal.org/node/1234567.
 */
class SeeReferences {

  /**
   * The default limit.
   *
   * @see static::limit().
   */
  public const LIMIT = 10;

  /**
   * Limits the result.
   *
   * @see \Drupal\corpus\SeeReferences::LIMIT
   */
  public function limit(): int {
    return self::LIMIT;
  }

}
