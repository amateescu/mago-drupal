<?php

/**
 * @file
 * The classes that drupal/global-drupal-call and drupal/static-entity-load
 * check.
 */

declare(strict_types=1);

namespace {
    class Drupal
    {
        public static function service(string $id): object
        {
            return new \stdClass();
        }
    }
}

namespace Drupal\Core\DependencyInjection {
    interface ContainerInjectionInterface {}
}

namespace Drupal\Core\Entity {
    interface EntityInterface
    {
        public static function load(int|string $id): ?static;
    }
}

namespace Drupal\user\Entity {
    use Drupal\Core\Entity\EntityInterface;

    final class User implements EntityInterface
    {
        public static function load(int|string $id): ?static
        {
            return null;
        }
    }
}
