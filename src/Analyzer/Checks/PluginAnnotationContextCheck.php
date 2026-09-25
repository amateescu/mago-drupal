<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Checks;

use amateescu\MagoDrupal\Internal\AnnotatedDeclarations;
use amateescu\MagoDrupal\Internal\ClassFacts;
use Closure;

use function array_key_exists;
use function strtolower;

/**
 * Reports a plugin annotation that still declares contexts under `context`.
 *
 * Ports phpstan-drupal's PluginAnnotationContextDefinitionsRule. The key was
 * renamed to `context_definitions` in Drupal 8.7 and removed in 9.0. The
 * annotation itself is read by the scan; this check reports on the class.
 *
 * @internal
 */
final class PluginAnnotationContextCheck implements MetadataCheck
{
    public const CODE = 'plugin-annotation-context';

    /**
     * @param Closure(): AnnotatedDeclarations $annotated
     */
    public function __construct(
        private readonly Closure $annotated,
    ) {}

    public function mentionsAny(): array
    {
        return [];
    }

    public function check(ClassFacts $class, Reporter $reporter): void
    {
        if (!array_key_exists(strtolower($class->name()), ($this->annotated)()->contextKeyed)) {
            return;
        }

        $reporter->error(self::CODE, Reporter::issue(
            "The plugin annotation on {$class->name()} declares contexts under \"context\".",
            $class->class->nameLocation ?? $class->class->location,
            'Use the context_definitions key, or move the plugin to an attribute.',
            'https://www.drupal.org/node/3016699',
        ));
    }
}
