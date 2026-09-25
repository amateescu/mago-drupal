<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Checks;

use amateescu\MagoDrupal\Internal\ClassFacts;
use amateescu\MagoDrupal\Internal\HookFunctions;
use Closure;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Metadata\ParameterMetadata;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\SourceLocation;
use Mago\Sdk\Span;

/**
 * Reports `hook_entity_operation` implementations without the
 * CacheableMetadata parameter Drupal 11.3 added.
 *
 * Ports phpstan-drupal's HookEntityOperationCacheabilityRule. Whether the hook
 * has the parameter is read off core's own `hook_entity_operation()`
 * documentation function, so no Drupal version table is kept here.
 *
 * @internal
 */
final class EntityOperationCacheabilityCheck implements MetadataCheck
{
    public const CODE = 'hook-entity-operation-cacheability';

    /**
     * Accepted parameter type names; a contravariant interface works too.
     */
    public const CACHEABLE_METADATA = ['CacheableMetadata', 'CacheableDependencyInterface'];

    public const LINK = 'https://www.drupal.org/node/3533080';

    /**
     * Hook name to the position of the cacheability parameter.
     */
    public const POSITIONS = [
        'entity_operation' => 1,
        'entity_operation_alter' => 2,
    ];

    /**
     * @param Closure(Codebase): HookFunctions $hooks
     */
    public function __construct(
        private readonly Closure $hooks,
    ) {}

    public function mentionsAny(): array
    {
        return [];
    }

    public function check(ClassFacts $class, Reporter $reporter): void
    {
        $hooks = ($this->hooks)($class->codebase);
        foreach (HookMethods::of($class) as [$hook, $method]) {
            $location = $method->nameLocation ?? $method->location;
            $position = self::missing($hooks, $hook, HookMethods::parameters($method));
            if ($position === null || $location === null) {
                continue;
            }

            $reporter->error(self::CODE, self::issue(HookMethods::label($method), $hook, $position, $location));
        }
    }

    /**
     * The position of the cacheability parameter an implementation of the
     * hook lacks, or null when it has it or the hook takes none. Core's
     * `hook_<name>()` documentation must declare the parameter.
     *
     * @param list<ParameterMetadata> $parameters
     */
    public static function missing(HookFunctions $hooks, string $hook, array $parameters): ?int
    {
        $position = self::POSITIONS[$hook] ?? null;
        if (
            $position === null
            || ($hooks->parameterCount("hook_{$hook}") ?? 0) <= $position
            || HookMethods::typed($parameters[$position] ?? null, self::CACHEABLE_METADATA)
        ) {
            return null;
        }

        return $position;
    }

    /**
     * The issue for an implementation without the cacheability parameter.
     * The suggested default keeps it working on core before 11.3, which
     * passes no such argument.
     */
    public static function issue(string $name, string $hook, int $position, Span|SourceLocation $where): Issue
    {
        return Reporter::issue(
            "{$name}() implements hook_{$hook} without the CacheableMetadata parameter added in Drupal 11.3.",
            $where,
            'Add `?\Drupal\Core\Cache\CacheableMetadata $cacheability = NULL` at position '
            . (string) ($position + 1)
            . '.',
            self::LINK,
        );
    }
}
