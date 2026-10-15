<?php

/**
 * @file
 * Core APIs whose docblocks say less than the code does, as core documents them.
 */

declare(strict_types=1);

namespace Drupal\Component\Render {
    interface MarkupInterface extends \Stringable {}
}

namespace Drupal\Component\Assertion {
    class Inspector
    {
        public static function assertAll(callable $callable, $traversable)
        {
            return true;
        }

        public static function assertAllStrings($traversable)
        {
            return true;
        }

        public static function assertAllNumeric($traversable)
        {
            return true;
        }

        public static function assertStrictArray($array)
        {
            return true;
        }

        public static function assertAllCallable($traversable)
        {
            return true;
        }

        public static function assertAllObjects($traversable, string ...$classes)
        {
            return true;
        }

        public static function assertStringable($string)
        {
            return true;
        }
    }
}

namespace Drupal\user {
    /**
     * @phpstan-type PermissionInfo array{
     *   title: string,
     *   description?: string|\Stringable,
     *   "restrict access"?: bool,
     *   warning?: string|\Stringable,
     *   dependencies?: array,
     *   provider: string,
     * }
     * @phpstan-type PermissionInfoList array<string, PermissionInfo>
     */
    interface PermissionHandlerInterface
    {
        /**
         * @return PermissionInfoList
         */
        public function getPermissions();

        /**
         * @param string $module_name
         * @return bool
         */
        public function moduleProvidesPermissions($module_name);
    }
}

namespace Drupal\Core\Queue {
    interface QueueInterface
    {
        /**
         * @return bool|object
         */
        public function claimItem(int $lease_time = 3600);
    }
}

namespace Drupal\Core\File {
    interface FileSystemInterface
    {
        /**
         * @return array
         */
        public function scanDirectory(string $dir, string $mask, array $options = []);
    }
}

namespace Drupal\Core\Extension {
    interface ModuleInstallerInterface
    {
        /**
         * @return string[][]
         */
        public function validateUninstall(array $module_list);
    }

    interface ModuleUninstallValidatorInterface
    {
        /**
         * @param string $module
         * @return string[]
         */
        public function validate($module);
    }
}

namespace Drupal\Component\Plugin\Derivative {
    interface DeriverInterface
    {
        /**
         * @param array|\Drupal\Component\Plugin\Definition\PluginDefinitionInterface $base_plugin_definition
         * @return array
         */
        public function getDerivativeDefinitions($base_plugin_definition);
    }
}

namespace Symfony\Component\EventDispatcher {
    interface EventSubscriberInterface
    {
        /**
         * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
         */
        public static function getSubscribedEvents();
    }
}

namespace Drupal\Core\Entity {
    class ContentEntityForm extends \Drupal\Core\Form\FormBase
    {
        /**
         * {@inheritdoc}
         */
        public static function create(\Symfony\Component\DependencyInjection\ContainerInterface $container)
        {
            return new static();
        }
    }

    trait EntityTypeEventSubscriberTrait
    {
        /**
         * @return array
         */
        public static function getEntityTypeEvents()
        {
            return [];
        }
    }
}
