<?php

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationManager;

use function t;

// @mago-expect lint:drupal/function-comment
function plain_literal(): string
{
    return t('Save configuration');
}

// @mago-expect lint:drupal/function-comment
function placeholder(string $name): string
{
    return t('Hello @name', ['@name' => $name]);
}

// @mago-expect lint:drupal/function-comment
function concatenated(string $name): string
{
    // @mago-expect lint:drupal/translatable-string
    return t('Hello ' . $name);
}

// @mago-expect lint:drupal/function-comment
function interpolated(string $name): string
{
    // @mago-expect lint:drupal/translatable-string
    return t("Hello {$name}");
}

// @mago-expect lint:drupal/function-comment
function padded(): string
{
    // @mago-expect lint:drupal/translatable-string
    return t('Save ');
}

// @mago-expect lint:drupal/function-comment
function empty_string(): string
{
    // @mago-expect lint:drupal/translatable-string
    return t('');
}

// @mago-expect lint:drupal/function-comment
function variable_message(string $message): string
{
    // @mago-expect lint:drupal/translatable-string
    return t($message);
}

// @mago-expect lint:drupal/function-comment
function named_argument_is_not_an_empty_call(): string
{
    return t(string: 'Save configuration');
}

// @mago-expect lint:drupal/function-comment
function markup_object(string $name): TranslatableMarkup
{
    // @mago-expect lint:drupal/translatable-string
    return new TranslatableMarkup('Hello ' . $name);
}

// @mago-expect lint:drupal/function-comment
function markup_object_is_fine(string $name): TranslatableMarkup
{
    return new TranslatableMarkup('Hello @name', ['@name' => $name]);
}

// @mago-expect lint:drupal/function-comment
function qualified_markup_object(string $name): TranslatableMarkup
{
    // @mago-expect lint:drupal/translatable-string
    return new \Drupal\Core\StringTranslation\TranslatableMarkup('Hello ' . $name);
}

// @mago-expect lint:drupal/function-comment
function nullsafe_method_call(?TranslationManager $translation, string $name): string
{
    // @mago-expect lint:drupal/translatable-string
    return (string) $translation?->t('Hello ' . $name);
}
