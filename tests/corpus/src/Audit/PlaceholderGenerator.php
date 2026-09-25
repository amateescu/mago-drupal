<?php

/**
 * @file
 * Stands in for the core class that filters render arrays by key.
 */

declare(strict_types=1);

namespace Drupal\Core\Render;

/**
 * Passes #lazy_builder through array_intersect_key() with a boolean value.
 */
final class PlaceholderGenerator {

  /**
   * Keeps only the placeholder keys.
   */
  public function keep(array $element): array {
    return array_intersect_key($element, ['#lazy_builder' => TRUE]);
  }

}
