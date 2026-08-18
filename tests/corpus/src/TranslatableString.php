<?php

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationManager;

use function t;

function plain_literal(): string
{
    return t('Save configuration');
}

function placeholder(string $name): string
{
    return t('Hello @name', ['@name' => $name]);
}

function concatenated(string $name): string
{
    // @mago-expect lint:drupal/translatable-string
    return t('Hello ' . $name);
}

function interpolated(string $name): string
{
    // @mago-expect lint:drupal/translatable-string
    return t("Hello {$name}");
}

function padded(): string
{
    // @mago-expect lint:drupal/translatable-string
    return t('Save ');
}

function empty_string(): string
{
    // @mago-expect lint:drupal/translatable-string
    return t('');
}

function variable_message(string $message): string
{
    // @mago-expect lint:drupal/translatable-string
    return t($message);
}

function named_argument_is_not_an_empty_call(): string
{
    return t(string: 'Save configuration');
}

function markup_object(string $name): TranslatableMarkup
{
    // @mago-expect lint:drupal/translatable-string
    return new TranslatableMarkup('Hello ' . $name);
}

function markup_object_is_fine(string $name): TranslatableMarkup
{
    return new TranslatableMarkup('Hello @name', ['@name' => $name]);
}

function qualified_markup_object(string $name): TranslatableMarkup
{
    // @mago-expect lint:drupal/translatable-string
    return new \Drupal\Core\StringTranslation\TranslatableMarkup('Hello ' . $name);
}

function nullsafe_method_call(?TranslationManager $translation, string $name): string
{
    // @mago-expect lint:drupal/translatable-string
    return (string) $translation?->t('Hello ' . $name);
}
