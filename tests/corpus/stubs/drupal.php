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

        public static function service(string $id): object
        {
            throw new \RuntimeException('stub');
        }

        public static function hasService(string $id): bool
        {
            return false;
        }

        public static function classResolver(?string $class = null): object
        {
            throw new \RuntimeException('stub');
        }

        public static function config(string $name): \Drupal\Core\Config\ImmutableConfig
        {
            throw new \RuntimeException('stub');
        }

        public static function entityQuery(string $entity_type, string $conjunction = 'AND'): \Drupal\Core\Entity\Query\QueryInterface
        {
            throw new \RuntimeException('stub');
        }

        public static function entityQueryAggregate(string $entity_type, string $conjunction = 'AND'): \Drupal\Core\Entity\Query\QueryAggregateInterface
        {
            throw new \RuntimeException('stub');
        }

        public static function configFactory(): \Drupal\Core\Config\ConfigFactoryInterface
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
    class Thing
    {
        public function onlyOnThing(): void {}
    }

    final class SealedThing {}

    class Other
    {
        public function onlyOnOther(): void {}
    }
}

namespace Drupal\corpus {
    #[\Attribute]
    class CorpusAttribute {}
}

namespace Psr\Container {
    interface ContainerInterface
    {
        public function get(string $id): mixed;

        public function has(string $id): bool;
    }
}

namespace Symfony\Component\DependencyInjection {
    interface ContainerInterface extends \Psr\Container\ContainerInterface
    {
        public const RUNTIME_EXCEPTION_ON_INVALID_REFERENCE = 0;
        public const EXCEPTION_ON_INVALID_REFERENCE = 1;
        public const NULL_ON_INVALID_REFERENCE = 2;
        public const IGNORE_ON_INVALID_REFERENCE = 3;
        public const IGNORE_ON_UNINITIALIZED_REFERENCE = 4;

        public function get(string $id, int $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE): ?object;
    }
}

namespace Drupal\Core\DependencyInjection {
    interface ContainerInterface extends \Symfony\Component\DependencyInjection\ContainerInterface {}

    interface ClassResolverInterface
    {
        public function getInstanceFromDefinition(string $definition): object;
    }

    class Container implements ContainerInterface
    {
        public function get(string $id, int $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE): ?object
        {
            return null;
        }

        public function has(string $id): bool
        {
            return false;
        }
    }
}

namespace Symfony\Component\DependencyInjection {
    class Definition
    {
        public function __construct(?string $class = null, array $arguments = []) {}

        public function setClass(?string $class): static
        {
            return $this;
        }

        public function addTag(string $name, array $attributes = []): static
        {
            return $this;
        }
    }

    class Alias
    {
        public function __construct(string $id, bool $public = false) {}
    }
}

namespace Drupal\Core\DependencyInjection {
    class ContainerBuilder
    {
        public function register(string $id, ?string $class = null): \Symfony\Component\DependencyInjection\Definition
        {
            return new \Symfony\Component\DependencyInjection\Definition($class);
        }

        public function setDefinition(string $id, \Symfony\Component\DependencyInjection\Definition $definition): \Symfony\Component\DependencyInjection\Definition
        {
            return $definition;
        }

        public function setAlias(string $alias, string|\Symfony\Component\DependencyInjection\Alias $id): \Symfony\Component\DependencyInjection\Alias
        {
            return new \Symfony\Component\DependencyInjection\Alias($alias);
        }
    }

    abstract class ServiceProviderBase
    {
        public function register(ContainerBuilder $container): void {}
    }
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

namespace Symfony\Component\HttpFoundation {
    class Response {}

    class RedirectResponse extends Response
    {
        public function __construct(string $url) {}
    }
}
