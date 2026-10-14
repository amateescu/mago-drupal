<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function array_intersect_key;
use function array_is_list;
use function array_key_exists;
use function array_reverse;
use function array_slice;
use function count;
use function is_array;
use function is_int;
use function preg_match;
use function strtolower;

/**
 * How the container builds the services of each class: the methods their
 * `calls:` invoke and the number of constructor arguments they get.
 *
 * A `parent:` child is resolved the way Symfony's ResolveChildDefinitionsPass
 * merges it: the parent's calls come first, the child's own numeric
 * arguments are appended to the parent's and an `index_N` key replaces one.
 * Keys are lowercased class names.
 *
 * @internal
 *
 * @phpstan-import-type Definition from ServiceYaml
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class ServiceWiring
{
    /**
     * Tags whose compiler pass adds one constructor argument to the service
     * carrying them. StackedKernelPass and StackedSessionHandlerPass prepend
     * the handler the service wraps, and TaggedHandlersPass appends the ids of
     * the services a `service_id_collector` collects.
     */
    private const ARGUMENT_TAGS = [
        'http_middleware' => true,
        'session_handler_proxy' => true,
        'service_id_collector' => true,
    ];

    /**
     * Keys that make a factory build the service instead of the constructor.
     * Drupal's loader reads all four.
     */
    private const FACTORY_KEYS = [
        'factory' => true,
        'factory_class' => true,
        'factory_method' => true,
        'factory_service' => true,
    ];

    /**
     * A key the resolved child uses to replace one of its parent's arguments.
     */
    private const REPLACED = '/^index_(\d+)$/';

    /**
     * @param array<string, array<string, true>> $calls Lowercased method names
     *   by class.
     * @param array<string, list<ServiceArguments>> $arguments The services of
     *   each class whose argument count is known.
     */
    private function __construct(
        private readonly array $calls,
        private readonly array $arguments,
    ) {}

    /**
     * @param array<non-empty-string, Definition> $definitions
     */
    public static function fromDefinitions(array $definitions): self
    {
        $calls = [];
        $arguments = [];
        foreach (ServiceResolver::graph($definitions) as $id => $node) {
            $class = $node instanceof ServiceDefinition ? $node->class : null;
            $lineage = $class === null ? null : ServiceResolver::lineage($id, $definitions);
            if ($class === null || $lineage === null) {
                continue;
            }

            $key = strtolower($class);
            foreach ($lineage as $definition) {
                foreach (self::callNames($definition) as $method) {
                    $calls[$key][$method] = true;
                }
            }

            $counted = self::counted($id, $lineage);
            if ($counted !== null) {
                $arguments[$key][] = $counted;
            }
        }

        return new self($calls, $arguments);
    }

    /**
     * Lowercased names of the methods the `calls:` of any service of the
     * class invoke, its parents' included.
     *
     * @return array<string, true>
     */
    public function calls(string $class): array
    {
        return $this->calls[strtolower($class)] ?? [];
    }

    /**
     * The services of the class whose constructor arguments can be counted.
     *
     * @return list<ServiceArguments>
     */
    public function arguments(string $class): array
    {
        return $this->arguments[strtolower($class)] ?? [];
    }

    /**
     * Whether a service of the class has a known argument count.
     */
    public function hasArguments(string $class): bool
    {
        return array_key_exists(strtolower($class), $this->arguments);
    }

    /**
     * The method names of one definition's own `calls:`, in either form the
     * loader reads: `[name, [...]]` or `{method: name, arguments: [...]}`.
     *
     * @param array<array-key, mixed> $definition
     * @return list<string>
     */
    private static function callNames(array $definition): array
    {
        $names = [];
        /** @var mixed $call */
        foreach (Shape::array($definition['calls'] ?? null) ?? [] as $call) {
            $method = is_array($call) ? Shape::nonEmptyString($call['method'] ?? $call[0] ?? null) : null;
            if ($method !== null) {
                $names[] = strtolower($method);
            }
        }

        return $names;
    }

    /**
     * The arguments the container passes to the constructor, or null when
     * something other than the services files decides them: a factory,
     * autowiring, named arguments, a synthetic service, or a definition
     * that a service provider wrote.
     *
     * @param non-empty-string $id
     * @param non-empty-list<array<array-key, mixed>> $lineage The definition,
     *   then its parents.
     */
    private static function counted(string $id, array $lineage): ?ServiceArguments
    {
        $own = $lineage[0];
        $module = Shape::nonEmptyString($own[ServiceYaml::MODULE] ?? null);
        if ($module === null || ($own['synthetic'] ?? false) === true) {
            return null;
        }

        foreach ($lineage as $definition) {
            if (
                !array_key_exists(ServiceYaml::MODULE, $definition)
                || ($definition['autowire'] ?? false) === true
                || array_intersect_key($definition, self::FACTORY_KEYS) !== []
            ) {
                return null;
            }
        }

        $ancestry = array_reverse($lineage);
        $count = self::listed($ancestry[0]['arguments'] ?? null);
        foreach (array_slice($ancestry, offset: 1) as $child) {
            $count = $count === null ? null : self::merged($count, $child['arguments'] ?? null);
        }

        return $count === null
            ? null
            : new ServiceArguments($id, $module . '.services.yml', $count + self::taggedArguments($own));
    }

    /**
     * The argument count of a definition without a parent, which has to be a
     * plain list.
     *
     * @return int<0, max>|null
     */
    private static function listed(mixed $arguments): ?int
    {
        if ($arguments === null) {
            return 0;
        }

        return is_array($arguments) && array_is_list($arguments) ? count($arguments) : null;
    }

    /**
     * The argument count after a child's own arguments: numeric keys are
     * appended to the parent's, an `index_N` key replaces one.
     *
     * @param int<0, max> $count The parent's count.
     * @return int<0, max>|null
     */
    private static function merged(int $count, mixed $arguments): ?int
    {
        if ($arguments === null) {
            return $count;
        }

        if (!is_array($arguments)) {
            return null;
        }

        foreach ($arguments as $key => $_) {
            if (is_int($key)) {
                $count++;
                continue;
            }

            // A named argument, or an index the arguments so far do not
            // have, which the container refuses to compile.
            $matches = [];
            if (preg_match(self::REPLACED, $key, $matches) !== 1 || (int) $matches[1] >= $count) {
                return null;
            }
        }

        return $count;
    }

    /**
     * The arguments compiler passes add for the definition's own tags; a
     * child takes no tags from its parent.
     *
     * @param array<array-key, mixed> $definition
     * @return int<0, max>
     */
    private static function taggedArguments(array $definition): int
    {
        $added = 0;
        /** @var mixed $tag */
        foreach (Shape::array($definition['tags'] ?? null) ?? [] as $tag) {
            $name = is_array($tag) ? Shape::string($tag['name'] ?? null) : Shape::string($tag);
            if ($name !== null && (self::ARGUMENT_TAGS[$name] ?? false)) {
                $added++;
            }
        }

        return $added;
    }
}
