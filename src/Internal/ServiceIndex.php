<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function array_key_exists;
use function count;
use function ltrim;
use function str_starts_with;
use function strtolower;

/**
 * Maps container service ids to the class the container hands back.
 *
 * Built from Drupal's `*.services.yml` files without booting Drupal.
 *
 * @internal
 *
 * @phpstan-import-type Definition from ServiceYaml
 */
final class ServiceIndex
{
    /**
     * Services `DrupalKernel::attachSynthetic()` adds outside any YAML file.
     */
    private const SYNTHETIC = [
        'kernel' => 'Drupal\Core\DrupalKernelInterface',
        'class_loader' => 'Composer\Autoload\ClassLoader',
        'service_container' => 'Drupal\Core\DependencyInjection\Container',
        'Symfony\Component\DependencyInjection\ContainerInterface' => 'Drupal\Core\DependencyInjection\Container',
    ];

    /**
     * Services of the kernel's bootstrap container, defined in PHP by
     * `DrupalKernel::$defaultBootstrapContainerDefinition`. `database` is in
     * core's services file as well. The hooks cannot tell the bootstrap
     * container from the real one, so these count as defined on both.
     */
    private const BOOTSTRAP = [
        'cache.container' => 'Drupal\Core\Cache\CacheBackendInterface',
        'cache_tags_provider.container' => 'Drupal\Core\Cache\CacheTagsChecksumInterface',
    ];

    /**
     * The lowercased classes the services are built from, read on first use.
     *
     * @var array<string, true>|null
     */
    private ?array $classes = null;

    /**
     * @param array<non-empty-string, ServiceDefinition> $services
     */
    private function __construct(
        private readonly array $services,
    ) {}

    /**
     * Parses the given files, later files overriding earlier ids.
     *
     * @param list<string> $paths
     */
    public static function fromFiles(array $paths): self
    {
        return self::fromDefinitions(ServiceYaml::load($paths));
    }

    /**
     * Builds the index from merged `services:` sections.
     *
     * @param array<non-empty-string, Definition> $definitions
     */
    public static function fromDefinitions(array $definitions): self
    {
        foreach ([...self::SYNTHETIC, ...self::BOOTSTRAP] as $id => $class) {
            $definitions[$id] ??= ['class' => $class];
        }

        $graph = ServiceResolver::graph($definitions);
        [$decorated, $inners] = ServiceDecorators::apply($graph, $definitions);

        return new self(ServiceResolver::resolve($decorated, $graph, $definitions, $inners));
    }

    /**
     * Returns the service behind an id, or null when the container has none.
     */
    public function get(string $id): ?ServiceDefinition
    {
        return $this->services[$id] ?? null;
    }

    /**
     * Whether the compiled container has the id at all.
     */
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }

    /**
     * Whether some id starts with the segment and a dot, as
     * `entity_type.manager` does for `entity_type`.
     */
    public function hasNamespace(string $segment): bool
    {
        foreach ($this->services as $id => $_) {
            if (str_starts_with($id, $segment . '.')) {
                return true;
            }
        }

        return false;
    }

    public function count(): int
    {
        return count($this->services);
    }

    /**
     * Whether some service is built from the class, by its `class:` key or
     * by an id that names the class. A decorated service counts with both
     * classes. Class names are compared without case, as PHP does.
     */
    public function hasClass(string $class): bool
    {
        if ($this->classes === null) {
            $this->classes = [];
            foreach ($this->services as $definition) {
                foreach ([$definition->class, $definition->undecoratedClass] as $name) {
                    if ($name !== null) {
                        $this->classes[strtolower(ltrim($name, characters: '\\'))] = true;
                    }
                }
            }
        }

        return array_key_exists(strtolower(ltrim($class, characters: '\\')), $this->classes);
    }
}
