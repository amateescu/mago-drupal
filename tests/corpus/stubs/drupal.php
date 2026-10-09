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
        public static function define(string $name, int $value): bool
        {
            return TRUE;
        }

        public static function state(): \Drupal\Core\State\StateInterface
        {
            throw new \RuntimeException('stub');
        }
    }
}

namespace Drupal\globals {
    function define(string $name, int $value): bool
    {
        return TRUE;
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

        public function formatPlural(int $count, string $singular, string $plural, array $args = []): string
        {
            return $count === 1 ? $singular : $plural;
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

namespace Drupal\corpus\Hook {
    #[\Attribute(\Attribute::TARGET_ALL | \Attribute::IS_REPEATABLE)]
    class Sample
    {
        public function __construct(public string $value = '') {}
    }
}

namespace Drupal\Core\Hook\Attribute {
    #[\Attribute(\Attribute::TARGET_ALL | \Attribute::IS_REPEATABLE)]
    class Hook
    {
        public function __construct(
            public string $hook = '',
            public string $method = '',
            public ?string $module = null,
            public int $priority = 0,
        ) {}
    }
}

namespace Drupal\Component\Annotation {
    interface AnnotationInterface {}

    abstract class Plugin implements AnnotationInterface {}
}

namespace Drupal\Core\Config\Entity {
    abstract class ConfigEntityBase {}
}

namespace Drupal\Component\Serialization {
    class Yaml
    {
        public static function decode(string $raw): mixed
        {
            return null;
        }
    }
}

namespace Symfony\Component\Yaml {
    class Yaml
    {
        public static function parse(string $input): mixed
        {
            return null;
        }
    }
}

namespace {
    function dpm(mixed $input, ?string $name = null): mixed
    {
        return $input;
    }

    function ksm(mixed ...$input): void {}
}
