<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Internal\FileMembers;
use amateescu\MagoDrupal\Internal\TestFiles;
use amateescu\MagoDrupal\Internal\Types;
use Mago\Sdk\Analyzer\Metadata\MethodMetadataProjection;
use Mago\Sdk\Analyzer\MethodCallAnalysisHook;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;

use function in_array;
use function strtolower;

/**
 * Reports `\Drupal::…` calls inside classes that can have services injected.
 *
 * Ports phpstan-drupal's GlobalDrupalDependencyInjectionRule, narrowed to
 * classes with a `create()` contract: descendants of ContainerInjectionInterface
 * and ContainerFactoryPluginInterface, so entities, typed data and stream
 * wrappers are left alone. Static methods have no injected services to use
 * instead and are skipped too, and so are constructors with a parameter that
 * accepts null (see isFallback()). The host hands over the `\Drupal::` calls
 * themselves, subclasses and instances of `Drupal` included; the enclosing
 * class and method come from their locations.
 *
 * @internal
 */
final class GlobalDrupalCallHook implements MethodCallAnalysisHook
{
    public const CODE = 'global-drupal-call';

    public const ANCESTORS = [
        'Drupal\Core\DependencyInjection\ContainerInjectionInterface',
        'Drupal\Core\Plugin\ContainerFactoryPluginInterface',
    ];

    public function getTargets(): array
    {
        return [MethodTarget::allMethods('Drupal')];
    }

    public function getRequirements(): array
    {
        return [];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        if (TestFiles::isTest($context->analysis->file)) {
            return;
        }

        $members = FileMembers::of($context->source);
        $class = $members->classAt($context->codebase, $context->source, $context->node->span);
        if ($class === null || !self::injectable($class->parentInterfaces)) {
            return;
        }

        $method = $members->methodAt($context->codebase, $class, $context->node->span);
        if ($method === null || $method->static === true || self::isFallback($method)) {
            return;
        }

        $context->report(
            Level::Warning,
            self::CODE,
            Issue::new(
                '\Drupal static calls inside a class bypass dependency injection.',
                $context->node->span,
            )->withHelp(
                'Inject the service through the constructor, or through create() for forms, controllers and plugins.',
            ),
        );
    }

    /**
     * Whether the method is a constructor with a parameter that accepts null.
     * Drupal's deprecation policy adds a new service to a constructor as a
     * nullable parameter, and the body falls back to `\Drupal::service()`
     * for callers that do not pass it yet. That call keeps old callers
     * working, so it is not a missed injection.
     */
    private static function isFallback(MethodMetadataProjection $method): bool
    {
        if ($method->constructor !== true) {
            return false;
        }

        foreach ($method->parameters ?? [] as $parameter) {
            if (
                Types::includesNull($parameter->declaredType?->type)
                || Types::includesNull($parameter->defaultType?->type)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $interfaces Lowercased.
     */
    private static function injectable(array $interfaces): bool
    {
        foreach (self::ANCESTORS as $ancestor) {
            if (in_array(strtolower($ancestor), $interfaces, strict: true)) {
                return true;
            }
        }

        return false;
    }
}
