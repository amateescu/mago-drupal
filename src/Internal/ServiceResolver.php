<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Closure;

use function array_key_exists;
use function is_array;
use function is_bool;
use function is_string;
use function ltrim;
use function preg_match;
use function str_replace;
use function str_starts_with;

/**
 * Turns raw service definitions into the class each id resolves to.
 *
 * Follows what the container compiler does with the shapes Drupal's YAML
 * actually uses: `alias:`, the `'@id'` string shorthand, `parent:`
 * inheritance, `abstract:` templates and the class-as-id shorthand. A service
 * whose class is not written down (a factory without `class:`, a
 * `%parameter%` class) keeps a null class rather than a guess.
 *
 * The work happens on a graph that maps each id to its alias target or to
 * the service it defines, the same two kinds of entry the container builder
 * keeps. ServiceDecorators rewrites that graph before the ids are resolved,
 * so an alias of a decorated id ends at the decorator.
 *
 * @internal
 *
 * @phpstan-import-type Definition from ServiceYaml
 * @phpstan-type Graph array<non-empty-string, non-empty-string|ServiceDefinition>
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 */
final class ServiceResolver
{
    /**
     * Upper bound on alias and parent chains, which guards against cycles.
     */
    private const MAX_DEPTH = 16;

    /**
     * The pattern the container's ResolveClassPass uses to take an id as the
     * class name: identifier segments with at least one namespace separator.
     */
    private const CLASS_ID = '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+(?:\\\\[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+)++$/';

    private function __construct() {}

    /**
     * Maps each id to its alias target, or to the service it defines.
     *
     * @param array<non-empty-string, Definition> $definitions
     * @return Graph
     */
    public static function graph(array $definitions): array
    {
        $graph = [];
        foreach ($definitions as $id => $definition) {
            $alias = self::aliasOf($definition);
            if ($alias !== null) {
                $graph[$id] = $alias;
                continue;
            }

            // Abstract templates are left out, since the container never
            // hands them back.
            if (is_array($definition) && ($definition['abstract'] ?? false) !== true) {
                $graph[$id] = new ServiceDefinition($id, self::classOf($id, $definitions));
            }
        }

        return $graph;
    }

    /**
     * Resolves every id of the graph to the service its aliases end at. Ids
     * that reach no service are left out.
     *
     * An id is private when it declares a service with `public: false`, set
     * on the definition or inherited through `parent:`, or when a decorator
     * moved a definition to it as `.inner`. Drupal keeps every alias
     * gettable, so an alias of a private service hands it back under the
     * alias.
     *
     * @param Graph $graph
     * @param Graph $undecorated The graph before any decorator was applied.
     * @param array<non-empty-string, Definition> $definitions
     * @param array<non-empty-string, true> $inners The private `.inner` ids decorators created.
     * @return array<non-empty-string, ServiceDefinition>
     */
    public static function resolve(array $graph, array $undecorated, array $definitions, array $inners = []): array
    {
        $services = [];
        foreach ($graph as $id => $_) {
            $service = self::follow($id, $graph);
            if ($service === null) {
                continue;
            }

            $original = self::follow($id, $undecorated);
            $services[$id] = new ServiceDefinition(
                $id,
                $service->class,
                self::deprecation($id, $definitions),
                $original !== null && $original !== $service ? $original->class : null,
                public: !array_key_exists($id, $inners)
                && self::inherited(
                    $id,
                    $definitions,
                    // Drupal keeps every alias gettable, whatever it says.
                    static fn(array $definition): ?bool => self::aliasOf($definition) === null
                        && is_bool($definition['public'] ?? null)
                            ? $definition['public']
                            : null,
                ) !== false,
            );
        }

        return $services;
    }

    /**
     * Returns the service a chain of aliases in the graph ends at.
     *
     * @param non-empty-string $id
     * @param Graph $graph
     */
    private static function follow(string $id, array $graph): ?ServiceDefinition
    {
        for ($depth = 0; $depth < self::MAX_DEPTH; $depth++) {
            $node = $graph[$id] ?? null;
            if (!is_string($node)) {
                return $node;
            }

            $id = $node;
        }

        return null;
    }

    /**
     * Returns the id of the concrete definition behind a chain of aliases.
     *
     * @param non-empty-string $id
     * @param array<non-empty-string, Definition> $definitions
     * @return non-empty-string|null
     */
    private static function followAliases(string $id, array $definitions): ?string
    {
        for ($depth = 0; $depth < self::MAX_DEPTH; $depth++) {
            if (!array_key_exists($id, $definitions)) {
                return null;
            }

            $alias = self::aliasOf($definitions[$id]);
            if ($alias === null) {
                return $id;
            }

            $id = $alias;
        }

        return null;
    }

    /**
     * Reads the alias target off a definition, or null for a real service.
     *
     * @param Definition $definition
     * @return non-empty-string|null
     */
    private static function aliasOf(array|string $definition): ?string
    {
        if (is_string($definition)) {
            return str_starts_with($definition, '@')
                ? Shape::nonEmptyString(ltrim($definition, characters: '@'))
                : null;
        }

        $alias = Shape::string($definition['alias'] ?? null);

        return $alias === null ? null : Shape::nonEmptyString(ltrim($alias, characters: '@'));
    }

    /**
     * Returns the definition a `parent:` key points at, through aliases the
     * way the container's `findDefinition()` does.
     *
     * @param array<array-key, mixed> $definition
     * @param array<non-empty-string, Definition> $definitions
     * @return non-empty-string|null
     */
    private static function parentOf(array $definition, array $definitions): ?string
    {
        $parent = Shape::nonEmptyString($definition['parent'] ?? null);

        return $parent === null ? null : self::followAliases($parent, $definitions);
    }

    /**
     * Reads the class of a non-alias definition, walking `parent:` chains.
     *
     * @param non-empty-string $id
     * @param array<non-empty-string, Definition> $definitions
     * @return non-empty-string|null
     */
    private static function classOf(string $id, array $definitions): ?string
    {
        for ($depth = 0; $depth < self::MAX_DEPTH; $depth++) {
            $definition = $definitions[$id] ?? null;
            if (!is_array($definition)) {
                return null;
            }

            $class = Shape::string($definition['class'] ?? null);
            if ($class !== null) {
                return self::className($class);
            }

            // `Drupal\foo\Bar: {}` names the class as the id. The container
            // sets that class before children inherit from their parent, so
            // it wins over the parent's class.
            if (preg_match(self::CLASS_ID, $id) === 1) {
                return self::className($id);
            }

            $parent = self::parentOf($definition, $definitions);
            if ($parent === null) {
                return null;
            }

            $id = $parent;
        }

        return null;
    }

    /**
     * Normalizes a class name, dropping `%foo.class%` placeholders that only
     * resolve at runtime.
     *
     * @return non-empty-string|null
     */
    private static function className(string $class): ?string
    {
        return str_starts_with($class, '%') ? null : Shape::nonEmptyString(ltrim($class, characters: '\\'));
    }

    /**
     * Reads the `deprecated` message of an id, inherited from its `parent:`
     * chain when it has none of its own. Both `%service_id%` and `%alias_id%`
     * name the id the caller asked for, as in `Definition` and `Alias`.
     *
     * @param non-empty-string $id
     * @param array<non-empty-string, Definition> $definitions
     */
    private static function deprecation(string $id, array $definitions): ?string
    {
        $message = self::inherited(
            $id,
            $definitions,
            static fn(array $definition): ?string => (
                Shape::nonEmptyString($definition['deprecated'] ?? null) ?? Shape::stringAt(
                    $definition,
                    'deprecated',
                    'message',
                )
            ),
        );

        return $message === null ? null : str_replace(['%service_id%', '%alias_id%'], $id, $message);
    }

    /**
     * The first value the reader finds on the id's definition, or on the
     * definitions up its `parent:` chain, the way a child definition inherits
     * from its parent. An alias has no parent, so only its own counts.
     *
     * @template T
     * @param non-empty-string $id
     * @param array<non-empty-string, Definition> $definitions
     * @param Closure(array<array-key, mixed>): (T|null) $read
     * @return T|null
     */
    private static function inherited(string $id, array $definitions, Closure $read): mixed
    {
        for ($depth = 0; $depth < self::MAX_DEPTH; $depth++) {
            $definition = $definitions[$id] ?? null;
            if (!is_array($definition)) {
                return null;
            }

            $value = $read($definition);
            if ($value !== null) {
                return $value;
            }

            $parent = self::aliasOf($definition) === null ? self::parentOf($definition, $definitions) : null;
            if ($parent === null) {
                return null;
            }

            $id = $parent;
        }

        return null;
    }
}
