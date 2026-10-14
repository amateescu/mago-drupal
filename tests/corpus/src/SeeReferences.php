<?php

declare(strict_types=1);

namespace Drupal\corpus;

// @mago-expect lint:drupal/doc-see-tag
/**
 * Coder reads the @see tags of function docblocks only.
 *
 * @see \Drupal\corpus\SeeReferences::limit().
 */
final class SeeReferences {

  // @mago-expect lint:drupal/doc-see-tag
  /**
   * The default limit.
   *
   * @see static::limit() for the use
   */
  public const LIMIT = 10;

  /**
   * The current limit.
   *
   * @var int
   *
   * @see static::LIMIT
   */
  protected int $limit = self::LIMIT;

  // @mago-expect lint:drupal/doc-see-tag
  /**
   * Returns the limit.
   *
   * @return int
   *   The limit.
   *
   * @see static::LIMIT.
   */
  public function limit(): int {
    return $this->limit;
  }

}
