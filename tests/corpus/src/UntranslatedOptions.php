<?php

declare(strict_types=1);

namespace Drupal\corpus;

use function t;

// @mago-expect lint:drupal/function-comment
function select_labels(): array {
  // The last item is reported too.
  // @mago-expect lint:drupal/untranslated-options(2)
  return [
    '#type' => 'select',
    '#options' => [
      'alpha' => 'Alpha label',
      'beta' => 'Beta label',
    ],
  ];
}

// @mago-expect lint:drupal/function-comment
function other_option_types(): array {
  // @mago-expect lint:drupal/untranslated-options(3)
  return [
    'radios' => ['#type' => 'radios', '#options' => ['one' => 'First label']],
    'checkboxes' => ['#type' => 'checkboxes', '#options' => ['two' => 'Second label']],
    'table' => ['#type' => 'tableselect', '#options' => ['three' => 'Third label']],
  ];
}

// @mago-expect lint:drupal/function-comment
function type_after_options(): array {
  // @mago-expect lint:drupal/untranslated-options
  return [
    '#options' => ['alpha' => 'Alpha label'],
    '#type' => 'select',
  ];
}

// @mago-expect lint:drupal/function-comment
function legacy_array_syntax(): array {
  // @mago-expect lint:drupal/untranslated-options
  return array(
    '#type' => 'select',
    '#options' => array('alpha' => 'Alpha label'),
  );
}

// @mago-expect lint:drupal/function-comment
function option_groups(): array {
  // @mago-expect lint:drupal/untranslated-options(2)
  return [
    '#type' => 'select',
    '#options' => [
      'Fruit' => ['apple' => 'Apple label'],
      'Meat' => array('beef' => 'Beef label'),
    ],
  ];
}

// @mago-expect lint:drupal/function-comment
function translated_labels(): array {
  return [
    '#type' => 'select',
    '#options' => [
      'alpha' => t('Alpha label'),
      'beta' => t('Beta label'),
    ],
  ];
}

// @mago-expect lint:drupal/function-comment
function values_that_are_not_labels(string $name, array $labels): array {
  return [
    '#type' => 'select',
    '#options' => [
      'number' => '1234',
      'decimal' => '12.5',
      'short' => 'abc',
      'concatenated' => 'Alpha' . ' label',
      'interpolated' => "Hello $name",
      'variable' => $name,
      'group' => $labels,
      'Beta label',
    ],
  ];
}

// @mago-expect lint:drupal/function-comment
function value_of_four_characters(): array {
  // @mago-expect lint:drupal/untranslated-options
  return [
    '#type' => 'select',
    '#options' => ['four' => 'abcd'],
  ];
}

// @mago-expect lint:drupal/function-comment
function other_element_types(): array {
  return [
    'text' => ['#type' => 'textfield', '#options' => ['alpha' => 'Alpha label']],
    'none' => ['#options' => ['alpha' => 'Alpha label']],
  ];
}

// @mago-expect lint:drupal/function-comment
function type_of_the_sibling_is_not_used(): array {
  // The select below is the only element with the right type.
  // @mago-expect lint:drupal/untranslated-options
  return [
    'first' => ['#type' => 'textfield'],
    'second' => ['#type' => 'select', '#options' => ['x' => 'Xray label']],
  ];
}

// @mago-expect lint:drupal/function-comment
function type_of_the_parent_is_not_used(): array {
  return [
    '#type' => 'select',
    'child' => ['#options' => ['x' => 'Xray label']],
  ];
}

// @mago-expect lint:drupal/function-comment
function options_that_are_not_a_literal(array $options): array {
  return [
    '#type' => 'select',
    '#options' => $options,
    'again' => ['#type' => 'select', '#options' => array_map(strtoupper(...), $options)],
  ];
}

// @mago-expect lint:drupal/function-comment
function assigned_options(): array {
  $element = ['#type' => 'select'];
  $element['#options'] = ['alpha' => 'Alpha label'];

  return $element;
}
