<?php

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationManager;

use function t;

// @mago-expect lint:drupal/function-comment
function plain_literal(): string {
  return t('Save configuration');
}

// @mago-expect lint:drupal/function-comment
function placeholder(string $name): string {
  return t('Hello @name', ['@name' => $name]);
}

// @mago-expect lint:drupal/function-comment
function concatenated(string $name): string {
  // The padding of the first literal gets its own report, as in Coder.
  // @mago-expect lint:drupal/translatable-string(2)
  return t('Hello ' . $name);
}

// @mago-expect lint:drupal/function-comment
function concatenated_with_padded_last_literal(string $name): string {
  // Coder checks the padding of the first literal only.
  // @mago-expect lint:drupal/translatable-string
  return t('Hello' . $name . ' ');
}

// @mago-expect lint:drupal/function-comment
function interpolated(string $name): string {
  // @mago-expect lint:drupal/translatable-string
  return t("Hello {$name}");
}

// @mago-expect lint:drupal/function-comment
function padded(): string {
  // @mago-expect lint:drupal/translatable-string
  return t('Save ');
}

// @mago-expect lint:drupal/function-comment
function empty_string(): string {
  // @mago-expect lint:drupal/translatable-string
  return t('');
}

// @mago-expect lint:drupal/function-comment
function lone_quote_is_not_empty(): string {
  // Only the outer quotes are delimiters. The quote between them is the text.
  return t('"') . t("'");
}

// @mago-expect lint:drupal/function-comment
function variable_message(string $message): string {
  // @mago-expect lint:drupal/translatable-string
  return t($message);
}

// @mago-expect lint:drupal/function-comment
function named_argument_is_not_an_empty_call(): string {
  return t(string: 'Save configuration');
}

// @mago-expect lint:drupal/function-comment
function markup_object(string $name): TranslatableMarkup {
  // @mago-expect lint:drupal/translatable-string(2)
  return new TranslatableMarkup('Hello ' . $name);
}

// @mago-expect lint:drupal/function-comment
function markup_object_is_fine(string $name): TranslatableMarkup {
  return new TranslatableMarkup('Hello @name', ['@name' => $name]);
}

// @mago-expect lint:drupal/function-comment
function qualified_markup_object(string $name): TranslatableMarkup {
  // @mago-expect lint:drupal/translatable-string(2)
  // @mago-expect lint:drupal/fully-qualified-name
  return new \Drupal\Core\StringTranslation\TranslatableMarkup('Hello ' . $name);
}

// @mago-expect lint:drupal/function-comment
function nullsafe_method_call(?TranslationManager $translation, string $name): string {
  // @mago-expect lint:drupal/translatable-string(2)
  return (string) $translation?->t('Hello ' . $name);
}

// @mago-expect lint:drupal/function-comment
function nowdoc_message(): string {
  // A nowdoc holds no variable, so Coder 9 takes it as a literal.
  return t(<<<'TEXT'
    Save configuration
    TEXT);
}

// @mago-expect lint:drupal/function-comment
function empty_nowdoc(): string {
  // @mago-expect lint:drupal/translatable-string
  return t(<<<'TEXT'

    TEXT);
}

// @mago-expect lint:drupal/function-comment
function heredoc_message(): string {
  // @mago-expect lint:drupal/translatable-string
  return t(<<<TEXT
    Save configuration
    TEXT);
}

// @mago-expect lint:drupal/function-comment
function concatenated_after(string $name): string {
  // @mago-expect lint:drupal/translatable-string
  return t('Name') . ': ' . $name;
}

// @mago-expect lint:drupal/function-comment
function concatenated_after_markup(string $name): string {
  // Coder lets through a string that is only space, markup or a bracket.
  return t('Name') . ' ' . t('Other') . '<br>' . t('Last') . ' (' . $name;
}

// @mago-expect lint:drupal/function-comment
function concatenated_after_method(TranslationManager $translation): string {
  // @mago-expect lint:drupal/translatable-string
  return $translation->t('Name') . ' and more';
}

// @mago-expect lint:drupal/function-comment
function concatenated_after_variable_message(string $message): string {
  // Only the non-literal message is reported, as in Coder.
  // @mago-expect lint:drupal/translatable-string
  return t($message) . ': ';
}

// @mago-expect lint:drupal/function-comment
function concatenated_after_interpolation(string $name): string {
  // A double-quoted string with a variable is not a constant string.
  return t('Name') . ": {$name}";
}

// @mago-expect lint:drupal/function-comment
function concatenated_after_plural(TranslationManager $translation, int $count): string {
  // @mago-expect lint:drupal/translatable-string
  return $translation->formatPlural($count, '1 item', '@count items') . ' left';
}

// @mago-expect lint:drupal/function-comment
function escaped_apostrophe(): string {
  // @mago-expect lint:drupal/translatable-string
  return t('It\'s here');
}

// @mago-expect lint:drupal/function-comment
function escaped_double_quote(): string {
  // @mago-format-ignore-start
  // @mago-expect lint:drupal/translatable-string
  return t("Say \"hi\"");
  // @mago-format-ignore-end
}

// @mago-expect lint:drupal/function-comment
function escaped_quote_in_markup(): TranslatableMarkup {
  // @mago-expect lint:drupal/translatable-string
  return new TranslatableMarkup('It\'s here');
}

// @mago-expect lint:drupal/function-comment
function escaped_quote_in_concatenation(string $name): string {
  // @mago-expect lint:drupal/translatable-string(3)
  return t('It\'s ' . $name);
}

// @mago-expect lint:drupal/function-comment
function escaped_backslash_is_not_an_escaped_quote(): string {
  return t('Path \\') . t("Path \\");
}

// @mago-expect lint:drupal/function-comment
function both_quotes_need_the_escape(): string {
  return t('Say "it\'s"') . t("Say \"it's\"");
}

// @mago-expect lint:drupal/function-comment
function escaped_quote_in_plural(TranslationManager $translation, int $count): string {
  // Coder reads the quotes of t() and the markup classes only.
  return $translation->formatPlural($count, 'It\'s one', 'It\'s many');
}

// @mago-expect lint:drupal/function-comment
function escaped_quote_in_other_argument(): string {
  return t('Hello @name', ['@name' => 'O\'Brien']);
}

// @mago-expect lint:drupal/function-comment
function escaped_quote_in_capital_call(): string {
  // PHP ignores the case of a function name.
  // @mago-expect lint:drupal/translatable-string
  return T('It\'s here');
}

// @mago-expect lint:drupal/function-comment
function escaped_quote_in_interpolation(TranslationManager $object): string {
  // @mago-expect lint:drupal/translatable-string
  return "Value: {$object->t('It\'s here')}";
}
