<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Analyzer\Checks\DependencySerializationCheck;
use amateescu\MagoDrupal\Internal\ClassFacts;
use amateescu\MagoDrupal\Internal\FileMembers;
use Mago\Sdk\Analyzer\MethodCallAnalysisHook;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;

/**
 * Reports a logger channel taken from the factory in the constructor of a
 * class using DependencySerializationTrait.
 *
 * Ports phpstan-drupal's LoggerFromFactoryPropertyAssignmentRule. The trait
 * serializes services by id, and a channel fetched from the factory has none,
 * so the property it lands in cannot be restored. A `get()` call in a
 * constructor is taken as such an assignment.
 *
 * @internal
 */
final class LoggerFromFactoryHook implements MethodCallAnalysisHook
{
    public const CODE = 'logger-from-factory';

    private const FACTORIES = [
        'Drupal\Core\Logger\LoggerChannelFactoryInterface',
        'Drupal\Core\Logger\LoggerChannelFactory',
    ];

    public function getTargets(): array
    {
        $targets = [];
        foreach (self::FACTORIES as $factory) {
            $targets[] = MethodTarget::exact($factory, 'get');
        }

        return $targets;
    }

    public function getRequirements(): array
    {
        return [];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        $members = FileMembers::of($context->source);
        $class = $members->classAt($context->codebase, $context->source, $context->node->span);
        if (
            $class === null
            || $members->methodAt($context->codebase, $class, $context->node->span)?->constructor !== true
        ) {
            return;
        }

        $facts = new ClassFacts($class, $context->codebase);
        if (!$facts->composes(DependencySerializationCheck::TRAIT)) {
            return;
        }

        $context->report(
            Level::Error,
            self::CODE,
            Issue::new(
                'A logger channel fetched from the factory cannot be serialized by DependencySerializationTrait.',
                $context->node->span,
            )->withHelp(
                'Inject the channel service itself, for example logger.channel.<module>, so the trait can restore it by id.',
            ),
        );
    }
}
