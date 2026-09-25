<?php

/**
 * @file
 * Framework classes the class-level checks look for.
 */

declare(strict_types=1);

namespace Drupal\corpus\Nested {
    trait CrossTrait
    {
        private string $sneaky = 's';

        protected \Drupal\Core\Entity\EntityStorageInterface $traitStorage;
    }
}

namespace PHPUnit\Framework {
    interface Test {}

    abstract class Assert
    {
        final public static function assertNotEmpty(mixed $actual, string $message = ''): void {}

        final public static function assertEmpty(mixed $actual, string $message = ''): void {}
    }

    abstract class TestCase extends Assert implements Test
    {
        protected function setUp(): void {}

        final public static function once(): MockObject\Rule\InvocationOrder
        {
            return new MockObject\Rule\InvocationOrder();
        }

        /**
         * @template T of object
         * @param class-string<T> $type
         * @return MockObject\MockObject&T
         */
        protected function createMock(string $type): MockObject\MockObject
        {
            throw new \LogicException($type);
        }

        /**
         * @template T of object
         * @param class-string<T> $type
         * @return MockObject\Stub&T
         */
        protected function createStub(string $type): MockObject\Stub
        {
            throw new \LogicException($type);
        }

        /**
         * @template T of object
         * @param class-string<T> $type
         * @return \Prophecy\Prophecy\ObjectProphecy<T>
         */
        protected function prophesize(string $type): \Prophecy\Prophecy\ObjectProphecy
        {
            throw new \LogicException($type);
        }
    }
}

namespace Prophecy\Prophecy {
    /**
     * @template-covariant T of object
     */
    interface ProphecyInterface
    {
        /**
         * @return T
         */
        public function reveal();
    }

    class MethodProphecy
    {
        public function willReturn(mixed $value): static
        {
            return $this;
        }
    }

    /**
     * @template-covariant T of object
     * @template-implements ProphecyInterface<T>
     */
    class ObjectProphecy implements ProphecyInterface
    {
        /**
         * @return T
         */
        public function reveal()
        {
            throw new \LogicException();
        }

        /**
         * @template U of object
         * @param class-string<U> $interface
         * @return $this
         * @phpstan-this-out static<T&U>
         */
        public function willImplement($interface)
        {
            return $this;
        }

        /**
         * @param array<mixed> $arguments
         * @return MethodProphecy
         */
        public function __call(string $methodName, array $arguments)
        {
            throw new \LogicException($methodName);
        }
    }
}

namespace PHPUnit\Framework\MockObject\Rule {
    class InvocationOrder {}
}

namespace PHPUnit\Framework\MockObject\Builder {
    interface InvocationStubber
    {
        public function willReturn(mixed $value): static;
    }

    final class InvocationMocker implements InvocationStubber
    {
        public function method(mixed $constraint): static
        {
            return $this;
        }

        public function willReturn(mixed $value): static
        {
            return $this;
        }
    }
}

namespace PHPUnit\Framework\MockObject {
    /**
     * @method Builder\InvocationStubber method($constraint)
     */
    interface Stub {}

    /**
     * @method Builder\InvocationMocker method($constraint)
     */
    interface MockObject extends Stub
    {
        public function expects(Rule\InvocationOrder $invocationRule): Builder\InvocationMocker;
    }
}

namespace Drupal\Tests\corpus {
    abstract class LooseTestBase extends \PHPUnit\Framework\TestCase
    {
        public static $modules = ['corpus'];
    }
}

namespace {
    #[\Attribute(\Attribute::TARGET_FUNCTION)]
    class CorpusMarker {}
}

namespace Drupal\Tests {
    abstract class BrowserTestBase extends \PHPUnit\Framework\TestCase
    {
        protected $profile = 'testing';

        protected $defaultTheme;
    }
}

namespace Drupal\FunctionalTests\Update {
    abstract class UpdatePathTestBase extends \Drupal\Tests\BrowserTestBase {}
}

namespace Drupal\Core\Form {
    interface FormStateInterface {}

    abstract class FormBase
    {
        use \Drupal\Core\DependencyInjection\DependencySerializationTrait;
    }
}

namespace Drupal\Core\Cache {
    class CacheableMetadata {}

    interface CacheableDependencyInterface {}
}

namespace Drupal\Core\DependencyInjection {
    trait DependencySerializationTrait
    {
        public function __sleep(): array
        {
            return [];
        }
    }

    interface ContainerInjectionInterface {}
}

namespace Drupal\Core\Controller {
    abstract class ControllerBase implements \Drupal\Core\DependencyInjection\ContainerInjectionInterface {}
}

namespace Drupal\Core\Hook\Attribute {
    #[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
    class Hook
    {
        public function __construct(
            public readonly string $hook,
            public readonly ?string $method = null,
            public readonly ?string $module = null,
        ) {}
    }

    #[\Attribute(\Attribute::TARGET_FUNCTION)]
    class LegacyHook {}

    #[\Attribute(\Attribute::TARGET_FUNCTION)]
    class LegacyRequirementsHook {}
}

namespace Drupal\Core\Config {
    class FileStorage {}
}

namespace Drupal\Core\KeyValueStore {
    interface KeyValueStoreInterface {}
}

namespace Symfony\Component\HttpFoundation\Session\Storage {
    interface SessionStorageInterface {}
}

namespace Drupal\Core\TypedData {
    interface TypedDataInterface {}
}

namespace Drupal\Core\Plugin {
    class PluginBase extends \Drupal\Component\Plugin\PluginBase {}
}



namespace Drupal\Core\Plugin\Context {
    interface ContextInterface extends \Drupal\Core\Cache\CacheableDependencyInterface
    {
        public function addCacheableDependency(mixed $dependency): static;
    }
}

namespace Drupal\Core\Cache {
    interface RefinableCacheableDependencyInterface extends CacheableDependencyInterface
    {
        public function addCacheableDependency(mixed $other_object): static;
    }

    trait RefinableCacheableDependencyTrait
    {
        /**
         * {@inheritdoc}
         */
        public function addCacheTags(array $cache_tags)
        {
            return $this;
        }
    }

    class CacheableMetadataRefinable extends CacheableMetadata implements RefinableCacheableDependencyInterface
    {
        public function addCacheableDependency(mixed $other_object): static
        {
            return $this;
        }
    }
}

namespace Drupal\Core\Render {
    interface RendererInterface
    {
        public function addCacheableDependency(array &$elements, mixed $dependency): void;
    }
}

namespace Drupal\Core\Extension {
    interface ModuleHandlerInterface
    {
        public function loadInclude(string $module, string $type, ?string $name = null): string|false;
    }
}

namespace Drupal\Core\Logger {
    interface LoggerChannelInterface {}

    interface LoggerChannelFactoryInterface
    {
        public function get(string $channel): LoggerChannelInterface;
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

namespace Drupal\corpus\Nested {
    class Cacheable implements \Drupal\Core\Cache\CacheableDependencyInterface {}
}

namespace {
    function dpm(mixed $input, ?string $name = null): mixed
    {
        return $input;
    }
}
