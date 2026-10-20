<?php

declare(strict_types=1);

namespace Drupal\corpus;

/**
 * Has a formatPlural() method that is not the translation API.
 */
final class PluralFormatter {

  /**
   * Formats the plural form.
   */
  public function formatPlural(): string {
    return 'plural';
  }

}
