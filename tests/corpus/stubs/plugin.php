<?php

/**
 * @file
 * Plugin API signatures the corpus fixtures call.
 */

declare(strict_types=1);

namespace Drupal\Component\Plugin {
    interface PluginInspectionInterface
    {
        public function getPluginId(): string;
    }

    abstract class PluginBase implements PluginInspectionInterface
    {
        public function __construct(
            protected array $configuration,
            protected string $pluginId,
            protected mixed $pluginDefinition,
        ) {}

        public function getPluginId(): string
        {
            return $this->pluginId;
        }
    }

    interface PluginManagerInterface extends Factory\FactoryInterface {}

    abstract class PluginManagerBase implements PluginManagerInterface
    {
        public function createInstance(string $plugin_id, array $configuration = []): object
        {
            throw new \RuntimeException('stub');
        }
    }
}

namespace Drupal\Component\Plugin\Factory {
    interface FactoryInterface
    {
        public function createInstance(string $plugin_id, array $configuration = []): object;
    }
}

namespace Drupal\Component\Plugin\Attribute {
    #[\Attribute(\Attribute::TARGET_CLASS)]
    abstract class AttributeBase
    {
        public function __construct(
            public readonly string $id,
        ) {}
    }

    #[\Attribute(\Attribute::TARGET_CLASS)]
    class Plugin extends AttributeBase {}
}

namespace Drupal\Core\Plugin {
    class DefaultPluginManager extends \Drupal\Component\Plugin\PluginManagerBase
    {
        protected function alterInfo(string $alter_hook): void {}

        public function setCacheBackend(object $cache_backend, string $cache_key, array $cache_tags = []): void {}
    }
}

namespace Drupal\Core\Block {
    interface BlockPluginInterface extends \Drupal\Component\Plugin\PluginInspectionInterface
    {
        public function build(): array;
    }

    abstract class BlockBase extends \Drupal\Component\Plugin\PluginBase implements BlockPluginInterface {}

    interface BlockManagerInterface extends \Drupal\Component\Plugin\PluginManagerInterface {}

    class BlockManager extends \Drupal\Core\Plugin\DefaultPluginManager implements BlockManagerInterface {}
}

namespace Drupal\Core\Block\Plugin\Block {
    /**
     * Core's fallback block, present whenever core's blocks are scanned.
     */
    #[\Drupal\Core\Block\Attribute\Block(id: 'broken')]
    class Broken extends \Drupal\Core\Block\BlockBase
    {
        public function build(): array
        {
            return [];
        }

        public function onlyOnBroken(): void {}
    }
}

namespace Drupal\Core\Block\Attribute {
    #[\Attribute(\Attribute::TARGET_CLASS)]
    class Block extends \Drupal\Component\Plugin\Attribute\Plugin
    {
        public function __construct(
            public readonly string $id,
            public readonly ?object $admin_label = null,
            public readonly ?string $deriver = null,
        ) {}
    }
}

namespace Drupal\corpus\Attribute {
    /**
     * A module's own block attribute, still discovered by the block manager.
     */
    #[\Attribute(\Attribute::TARGET_CLASS)]
    class SpecialBlock extends \Drupal\Core\Block\Attribute\Block {}
}

namespace Drupal\Core\Condition {
    class ConditionManager extends \Drupal\Core\Plugin\DefaultPluginManager {}
}

namespace Drupal\Core\Render {
    class ElementInfoManager extends \Drupal\Core\Plugin\DefaultPluginManager {}
}

namespace Drupal\Core\Render\Attribute {
    #[\Attribute(\Attribute::TARGET_CLASS)]
    class RenderElement extends \Drupal\Component\Plugin\Attribute\Plugin
    {
        public function __construct(
            public readonly string $id,
        ) {}
    }
}

namespace Drupal\Core\Render\Element {
    abstract class RenderElementBase extends \Drupal\Core\Plugin\PluginBase {}
}

namespace Drupal\corpus\Plugin {
    /**
     * A manager a module derives from core's block manager.
     */
    class CorpusBlockManager extends \Drupal\Core\Block\BlockManager {}
}
