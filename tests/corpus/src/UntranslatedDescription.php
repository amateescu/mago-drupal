<?php

/**
 * @file
 * Exercises drupal/untranslated-description.
 */

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\Core\StringTranslation\TranslatableMarkup;

use function t;

/**
 * A plain string in an element, in either array syntax and quote style.
 */
function description_in_elements(): array {
  return [
    // @mago-expect lint:drupal/untranslated-description
    'a' => ['#type' => 'textfield', '#description' => 'Some untranslated text'],
    // @mago-expect lint:drupal/untranslated-description
    'b' => array('#description' => 'Double quoted text'),
    // @mago-expect lint:drupal/untranslated-description
    'c' => ['#description' => 'Other quotes'],
  ];
}

/**
 * A plain string assigned to the key.
 */
function description_assigned(): array {
  $form = ['b' => [], 'c' => ['#description' => 'Go']];
  // @mago-expect lint:drupal/untranslated-description
  $form['b']['#description'] = 'Assigned untranslated text';
  // @mago-expect lint:drupal/untranslated-description
  $form['#description'] = 'Top level text';
  $form['c']['#description'] .= ' and appended text';

  return $form;
}

/**
 * A concatenation that starts with a plain string.
 */
function description_concatenated(string $name): array {
  return [
    // @mago-expect lint:drupal/untranslated-description
    'a' => ['#description' => 'Prefix text ' . (string) t('more')],
    'b' => ['#description' => $name . ' text'],
  ];
}

/**
 * Values that aren't reported.
 */
function description_fine(array $form, string $name): array {
  return [
    'translated' => ['#description' => t('Translated text')],
    'markup' => ['#description' => new TranslatableMarkup('Translated text')],
    'short' => ['#description' => 'abc'],
    'tags' => ['#description' => '<em>abc</em>'],
    'link' => ['#description' => '<a href="http://example.com">x</a>'],
    'fallback' => ['#description' => $form['x'] ?? 'Fallback text here'],
    'interpolated' => ['#description' => "Hello {$name} there", '#title' => 'A plain title'],
    'nested' => ['#description' => ['#markup' => 'Nested markup text']],
    'ternary' => ['#description' => $name ? 'Yes text' : 'No text'],
    'format' => ['#description' => sprintf('Format %s', $name)],
    'other key' => ['#title' => 'A plain title'],
  ];
}
