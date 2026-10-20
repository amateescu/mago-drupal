<?php

declare(strict_types=1);

namespace Drupal\corpus;

// @mago-expect lint:drupal/doc-see-tag
/**
 * Coder reads the @see tags of function and property docblocks only.
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

  // Other text that ends in a period gets one issue, as in Coder.
  // @mago-expect lint:drupal/doc-see-tag(1)
  /**
   * Returns the default limit.
   *
   * @see static::LIMIT for the value.
   */
  public function defaultLimit(): int {
    return self::LIMIT;
  }

  // Coder reads `@see:` as a tag of its own and skips it.
  // @mago-expect lint:drupal/doc-see-tag(1)
  /**
   * Returns the limit link.
   *
   * @see: https://www.drupal.org/i/3536307
   */
  public function limitLink(): string {
    return 'https://www.drupal.org/i/3536307';
  }

  // The colon and the punctuation are two issues.
  // @mago-expect lint:drupal/doc-see-tag(2)
  /**
   * Returns the limit page.
   *
   * @see:https://www.drupal.org/i/3536307.
   */
  public function limitPage(): string {
    return 'https://www.drupal.org/i/3536307';
  }

  // A `@see:` with nothing after the colon is also empty.
  // @mago-expect lint:drupal/doc-see-tag(2)
  /**
   * Returns the limit name.
   *
   * @see:
   */
  public function limitName(): string {
    return 'limit';
  }

}

// @mago-expect lint:drupal/doc-see-tag(1)
/**
 * Coder reads `@see:` as a tag of its own in every docblock.
 *
 * @see: \Drupal\corpus\SeeReferences
 */
final class SeeColonReferences {

  // @mago-expect lint:drupal/doc-see-tag(2)
  /**
   * The colon in a property docblock.
   *
   * @var int
   *
   * @see:
   */
  public int $limit = 1;

}
