<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function array_key_exists;
use function is_array;
use function usort;

/**
 * Applies `decorates:` the way the container's DecoratorServicePass does.
 *
 * Decorators run from the highest `decoration_priority` down, in definition
 * order between equal priorities. Each one moves what the decorated id points
 * at to `<decorator id>.inner` (or `decoration_inner_name`) and makes the id
 * an alias of the decorator. So the id ends at the last decorator applied,
 * the lowest priority one, and every alias of the id follows it. A decorator
 * of a missing id is dropped for `decoration_on_invalid: ignore` and takes
 * the id over for `decoration_on_invalid: ~`. An `.inner` id that holds the
 * decorated definition is private in the compiled container, so apply()
 * names those too. One that holds an alias, as with stacked decorators,
 * stays gettable, as every alias does in Drupal.
 *
 * @internal
 *
 * @phpstan-import-type Definition from ServiceYaml
 * @phpstan-import-type Graph from ServiceResolver
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class ServiceDecorators
{
    private function __construct() {}

    /**
     * Returns the graph with every decorator applied, and the private
     * `.inner` ids the decorators created.
     *
     * @param Graph $graph
     * @param array<non-empty-string, Definition> $definitions
     * @return array{Graph, array<non-empty-string, true>}
     */
    public static function apply(array $graph, array $definitions): array
    {
        $inners = [];
        foreach (self::decorators($graph, $definitions) as [$_, $id, $decorated, $inner, $onInvalid]) {
            if (array_key_exists($decorated, $graph)) {
                if ($graph[$decorated] instanceof ServiceDefinition) {
                    $inners[$inner] = true;
                }

                $graph[$inner] = $graph[$decorated];
                $graph[$decorated] = $id;
                continue;
            }

            if ($onInvalid === 'ignore') {
                unset($graph[$id]);
                continue;
            }

            // With the default `exception` the container fails to compile,
            // and the decorator stays a plain service of its own id.
            if ($onInvalid === null) {
                $graph[$decorated] = $id;
            }
        }

        return [$graph, $inners];
    }

    /**
     * Lists the decorators in the order the container applies them.
     *
     * @param Graph $graph
     * @param array<non-empty-string, Definition> $definitions
     * @return list<array{int, non-empty-string, non-empty-string, non-empty-string, string|null}>
     */
    private static function decorators(array $graph, array $definitions): array
    {
        $decorators = [];
        foreach ($definitions as $id => $definition) {
            if (!is_array($definition) || !($graph[$id] ?? null) instanceof ServiceDefinition) {
                continue;
            }

            $decorated = Shape::nonEmptyString($definition['decorates'] ?? null);
            if ($decorated === null) {
                continue;
            }

            $decorators[] = [
                Shape::int($definition['decoration_priority'] ?? null) ?? 0,
                $id,
                $decorated,
                Shape::nonEmptyString($definition['decoration_inner_name'] ?? null) ?? $id . '.inner',
                // A YAML null (`~`) is its own behavior, not a missing key.
                array_key_exists('decoration_on_invalid', $definition)
                    ? Shape::string($definition['decoration_on_invalid'])
                    : 'exception',
            ];
        }

        // usort() is stable, so equal priorities keep definition order.
        usort($decorators, static fn(array $a, array $b): int => $b[0] <=> $a[0]);

        return $decorators;
    }
}
