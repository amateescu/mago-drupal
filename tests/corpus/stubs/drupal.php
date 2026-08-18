<?php

/**
 * @file
 * Signatures the corpus fixtures call, so the analyzer has something to check.
 *
 * The corpus is a linting workspace with no Drupal installed. These are loaded
 * as includes, which means Mago reads them for symbols but does not lint them.
 */

declare(strict_types=1);

namespace {
    function t(string $string, array $args = [], array $options = []): string
    {
        return $string;
    }

    function l(string $text, string $path, array $options = []): string
    {
        return $text;
    }

    function watchdog(string $type, string $message, array $variables = [], int $severity = 5): void {}

    function format_date(int $timestamp, string $type = 'medium'): string
    {
        return (string) $timestamp;
    }

    class Drupal
    {
        public static function state(): \Drupal\Core\State\StateInterface
        {
            throw new \RuntimeException('stub');
        }
    }
}

namespace Drupal\Core\State {
    interface StateInterface
    {
        public function get(string $key, mixed $default = null): mixed;

        public function set(string $key, mixed $value): void;

        public function delete(string $key): void;
    }
}

namespace Drupal\Core\StringTranslation {
    class TranslatableMarkup
    {
        public function __construct(
            protected string $string,
            protected array $arguments = [],
            protected array $options = [],
        ) {}
    }

    class TranslationManager
    {
        public function t(string $string, array $args = [], array $options = []): string
        {
            return $string;
        }
    }
}

namespace Drupal\corpus\Nested {
    class Thing {}
}

namespace Drupal\corpus {
    #[\Attribute]
    class CorpusAttribute {}
}
