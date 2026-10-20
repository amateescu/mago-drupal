<?php

namespace Drupal\corpus;

/**
 * Handles access to nodes.
 *
 * @see \Drupal\node\NodeAccessControlHandler
 */
class SeeColon {

  /**
   * The default limit.
   *
   * @see static::limit()
   */
  public const LIMIT = 10;

  /**
   * Limits the result.
   *
   * @see https://www.drupal.org/i/3536307
   * @see
   * @see static::LIMIT for the value.
   */
  public function limit(): int {
    return self::LIMIT;
  }

}
