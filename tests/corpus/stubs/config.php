<?php

/**
 * @file
 * Config API signatures the corpus fixtures call.
 */

declare(strict_types=1);

namespace Drupal\Core\Config {
    abstract class ConfigBase
    {
        public function get(string $key = ''): mixed
        {
            return null;
        }
    }

    class Config extends ConfigBase
    {
        public function set(string $key, mixed $value): static
        {
            return $this;
        }
    }

    class ImmutableConfig extends Config {}

    interface ConfigFactoryInterface
    {
        public function get(string $name): ImmutableConfig;

        public function getEditable(string $name): Config;
    }

    interface StorageInterface
    {
        public function read(string $name): array|bool;
    }
}

namespace Drupal\Core\Form {
    trait ConfigFormBaseTrait
    {
        protected function config(string $name): \Drupal\Core\Config\Config
        {
            throw new \RuntimeException('stub');
        }
    }

    abstract class ConfigFormBase
    {
        use ConfigFormBaseTrait;
    }
}
