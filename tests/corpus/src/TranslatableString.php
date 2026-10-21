<?php

/**
 * @file
 * Exercises drupal/translatable-string and drupal/translatable-literal.
 */

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationManager;

use function t;

// @mago-expect lint:drupal/missing-function-comment
function plain_literal(): TranslatableMarkup {
  return t('Save configuration');
}

// @mago-expect lint:drupal/missing-function-comment
function placeholder(string $name): TranslatableMarkup {
  return t('Hello @name', ['@name' => $name]);
}

// @mago-expect lint:drupal/missing-function-comment
function concatenated(string $name): TranslatableMarkup {
  // The padding of the first literal gets its own report, as in Coder.
  // @mago-expect lint:drupal/translatable-string(2)
  return t('Hello ' . $name);
}

// @mago-expect lint:drupal/missing-function-comment
function concatenated_with_padded_last_literal(string $name): TranslatableMarkup {
  // Coder checks the padding of the first literal only.
  // @mago-expect lint:drupal/translatable-string
  return t('Hello' . $name . ' ');
}

// @mago-expect lint:drupal/missing-function-comment
function interpolated(string $name): TranslatableMarkup {
  // @mago-expect lint:drupal/translatable-literal
  return t("Hello {$name}");
}

// @mago-expect lint:drupal/missing-function-comment
function padded(): TranslatableMarkup {
  // @mago-expect lint:drupal/translatable-string
  return t('Save ');
}

// @mago-expect lint:drupal/missing-function-comment
function empty_string(): TranslatableMarkup {
  // @mago-expect lint:drupal/translatable-string
  return t('');
}

// @mago-expect lint:drupal/missing-function-comment
function lone_quote_is_not_empty(): string {
  // Only the outer quotes are delimiters. The quote between them is the text.
  return (string) t('"') . (string) t("'");
}

// @mago-expect lint:drupal/missing-function-comment
function variable_message(string $message): TranslatableMarkup {
  // @mago-expect lint:drupal/translatable-literal
  return t($message);
}

// @mago-expect lint:drupal/missing-function-comment
function named_argument_is_not_an_empty_call(): TranslatableMarkup {
  return t(string: 'Save configuration');
}

// @mago-expect lint:drupal/missing-function-comment
function unpacked_argument_is_not_an_empty_call(array $arguments): TranslatableMarkup {
  // The call has no positional first argument to read. The analyzer does
  // not count the unpacked arguments.
  // @mago-expect analysis:too-few-arguments
  return t(...$arguments);
}

// @mago-expect lint:drupal/missing-function-comment
function first_class_callable(array $names): array {
  // The string extractor cannot see the strings that the callable gets.
  // @mago-expect lint:drupal/translatable-literal
  return array_map(t(...), $names);
}

// @mago-expect lint:drupal/missing-function-comment
function first_class_callable_method(TranslationManager $translation, array $names): array {
  // @mago-expect lint:drupal/translatable-literal
  return array_map($translation->t(...), $names);
}

// @mago-expect lint:drupal/missing-function-comment
function first_class_callable_plural(TranslationManager $translation): \Closure {
  // @mago-expect lint:drupal/translatable-literal
  return $translation->formatPlural(...);
}

// @mago-expect lint:drupal/missing-function-comment
function first_class_callable_with_a_comment(array $names): array {
  // @mago-format-ignore-start
  // @mago-expect lint:drupal/translatable-literal
  return array_map(t(/* Each name. */ ...), $names);
  // @mago-format-ignore-end
}

// @mago-expect lint:drupal/missing-function-comment
function first_class_callable_of_another_function(array $names): array {
  return array_map(trim(...), $names);
}

// @mago-expect lint:drupal/missing-function-comment
function markup_object(string $name): TranslatableMarkup {
  // @mago-expect lint:drupal/translatable-string(2)
  return new TranslatableMarkup('Hello ' . $name);
}

// @mago-expect lint:drupal/missing-function-comment
function markup_object_is_fine(string $name): TranslatableMarkup {
  return new TranslatableMarkup('Hello @name', ['@name' => $name]);
}

// @mago-expect lint:drupal/missing-function-comment
function qualified_markup_object(string $name): TranslatableMarkup {
  // @mago-expect lint:drupal/translatable-string(2)
  // @mago-expect lint:drupal/fully-qualified-name
  return new \Drupal\Core\StringTranslation\TranslatableMarkup('Hello ' . $name);
}

// @mago-expect lint:drupal/missing-function-comment
function nullsafe_method_call(?TranslationManager $translation, string $name): string {
  // @mago-expect lint:drupal/translatable-string(2)
  return (string) $translation?->t('Hello ' . $name);
}

// @mago-expect lint:drupal/missing-function-comment
function nowdoc_message(): TranslatableMarkup {
  // A nowdoc holds no variable, so Coder 9 takes it as a literal.
  return t(<<<'TEXT'
    Save configuration
    TEXT);
}

// @mago-expect lint:drupal/missing-function-comment
function empty_nowdoc(): TranslatableMarkup {
  // @mago-expect lint:drupal/translatable-string
  return t(<<<'TEXT'

    TEXT);
}

// @mago-expect lint:drupal/missing-function-comment
function heredoc_message(): TranslatableMarkup {
  // @mago-expect lint:drupal/translatable-literal
  return t(<<<TEXT
    Save configuration
    TEXT);
}

// @mago-expect lint:drupal/missing-function-comment
function concatenated_after(string $name): string {
  // @mago-expect lint:drupal/translatable-literal
  return (string) t('Name') . ': ' . $name;
}

// @mago-expect lint:drupal/missing-function-comment
function concatenated_after_markup(string $name): string {
  // Coder lets through a string that is only space, markup or a bracket.
  return (string) t('Name') . ' ' . (string) t('Other') . '<br>' . (string) t('Last') . ' (' . $name;
}

// @mago-expect lint:drupal/missing-function-comment
function concatenated_after_method(TranslationManager $translation): string {
  // @mago-expect lint:drupal/translatable-literal
  return $translation->t('Name') . ' and more';
}

// @mago-expect lint:drupal/missing-function-comment
function concatenated_after_variable_message(string $message): string {
  // Only the non-literal message is reported, as in Coder.
  // @mago-expect lint:drupal/translatable-literal
  return (string) t($message) . ': ';
}

// @mago-expect lint:drupal/missing-function-comment
function concatenated_after_interpolation(string $name): string {
  // A double-quoted string with a variable is not a constant string.
  return (string) t('Name') . ": {$name}";
}

// @mago-expect lint:drupal/missing-function-comment
function concatenated_after_plural(TranslationManager $translation, int $count): string {
  // @mago-expect lint:drupal/translatable-literal
  return $translation->formatPlural($count, '1 item', '@count items') . ' left';
}

// @mago-expect lint:drupal/missing-function-comment
function escaped_apostrophe(): TranslatableMarkup {
  // @mago-expect lint:drupal/translatable-string
  return t('It\'s here');
}

// @mago-expect lint:drupal/missing-function-comment
function escaped_double_quote(): TranslatableMarkup {
  // @mago-format-ignore-start
  // @mago-expect lint:drupal/translatable-string
  return t("Say \"hi\"");
  // @mago-format-ignore-end
}

// @mago-expect lint:drupal/missing-function-comment
function escaped_quote_in_markup(): TranslatableMarkup {
  // @mago-expect lint:drupal/translatable-string
  return new TranslatableMarkup('It\'s here');
}

// @mago-expect lint:drupal/missing-function-comment
function escaped_quote_in_concatenation(string $name): TranslatableMarkup {
  // @mago-expect lint:drupal/translatable-string(3)
  return t('It\'s ' . $name);
}

// @mago-expect lint:drupal/missing-function-comment
function escaped_backslash_is_not_an_escaped_quote(): string {
  return (string) t('Path \\') . (string) t("Path \\");
}

// @mago-expect lint:drupal/missing-function-comment
function both_quotes_need_the_escape(): string {
  return (string) t('Say "it\'s"') . (string) t("Say \"it's\"");
}

// @mago-expect lint:drupal/missing-function-comment
function escaped_quote_in_plural(TranslationManager $translation, int $count): string {
  // Coder reads the quotes of t() and the markup classes only.
  return $translation->formatPlural($count, 'It\'s one', 'It\'s many');
}

// @mago-expect lint:drupal/missing-function-comment
function escaped_quote_in_other_argument(): TranslatableMarkup {
  return t('Hello @name', ['@name' => 'O\'Brien']);
}

// @mago-expect lint:drupal/missing-function-comment
function escaped_quote_in_capital_call(): TranslatableMarkup {
  // PHP ignores the case of a function name.
  // @mago-expect lint:drupal/translatable-string
  return T('It\'s here');
}

// @mago-expect lint:drupal/missing-function-comment
function escaped_quote_in_interpolation(TranslationManager $object): string {
  // @mago-expect lint:drupal/translatable-string
  return "Value: {$object->t('It\'s here')}";
}

// @mago-expect lint:drupal/missing-function-comment
function multi_line_literal(): TranslatableMarkup {
  // The phpcs tokenizer splits a literal that spans lines into one token per
  // line, so Coder doesn't check its padding.
  return t('
    <p>Some text.</p>
  ');
}

// @mago-expect lint:drupal/missing-function-comment
function multi_line_literal_padded_on_one_side(): TranslatableMarkup {
  return t(' Some
    text.');
}

// @mago-expect lint:drupal/missing-function-comment
function multi_line_first_literal_of_concatenation(string $name): TranslatableMarkup {
  // @mago-expect lint:drupal/translatable-string(1)
  return t('Hello
    ' . $name);
}

// @mago-expect lint:drupal/missing-function-comment
function escaped_newline_at_the_start(): TranslatableMarkup {
  // PHP builds the same value as a line break in the source, and the literal
  // is on one line.
  // @mago-expect lint:drupal/translatable-string(1)
  return t("\nSome text.");
}

// @mago-expect lint:drupal/missing-function-comment
function plural_without_arguments(PluralFormatter $formatter): string {
  // StringTranslationTrait::formatPlural() needs three arguments, so this is
  // another method.
  return $formatter->formatPlural();
}
