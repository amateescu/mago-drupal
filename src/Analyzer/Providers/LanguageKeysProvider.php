<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

/**
 * Keys the language lists core returns by language code, typed as strings.
 *
 * Core documents both methods as `LanguageInterface[]` keyed by language code.
 * A language code matches `LanguageInterface::VALID_LANGCODE_REGEX`, which
 * starts with a letter, so PHP never turns one into an integer key. With
 * string keys, `array_keys()` and `foreach` hand a language code on to the
 * methods that take one.
 *
 * @internal
 */
final class LanguageKeysProvider implements MethodReturnTypeProvider
{
    private const LANGUAGE = 'Drupal\Core\Language\LanguageInterface';

    public function getTargets(): array
    {
        return [
            MethodTarget::exact('Drupal\Core\TypedData\TranslatableInterface', 'getTranslationLanguages'),
            MethodTarget::exact('Drupal\Core\Language\LanguageManagerInterface', 'getLanguages'),
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        return Type::array(Type::string(), Type::namedObject(self::LANGUAGE));
    }
}
