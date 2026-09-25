<?php

/**
 * @file
 * Language lists keyed by language code.
 */

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\TypedData\TranslatableInterface;

/**
 * Hands language codes on to code that takes a string.
 */
final class Languages {

  /**
   * A language code never becomes an integer key.
   */
  public function languageCodes(TranslatableInterface $entity, LanguageManagerInterface $language_manager): void {
    foreach (array_keys($entity->getTranslationLanguages()) as $langcode) {
      $this->wantsLangcode($langcode);
    }

    foreach ($language_manager->getLanguages() as $langcode => $language) {
      $this->wantsLangcode($langcode);
      $this->wantsLangcode($language->getId());
    }
  }

  /**
   * Takes a language code.
   */
  private function wantsLangcode(string $langcode): void {
  }

}
