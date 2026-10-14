<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Analyzer\Checks\DependencySerializationCheck;
use amateescu\MagoDrupal\Internal\ClassFacts;
use amateescu\MagoDrupal\Internal\FileMembers;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\MethodCallAnalysisHook;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;

use function max;
use function preg_match;
use function substr;

/**
 * Reports a logger channel taken from the factory in the constructor of a
 * class using DependencySerializationTrait.
 *
 * Ports phpstan-drupal's LoggerFromFactoryPropertyAssignmentRule. The trait
 * serializes services by id, and a channel fetched from the factory has none,
 * so the property it lands in cannot be restored. A `get()` call in a
 * constructor whose result is assigned to a property of `$this` is reported.
 *
 * @internal
 */
final class LoggerFromFactoryHook implements MethodCallAnalysisHook
{
    public const CODE = 'logger-from-factory';

    /**
     * The factory's interface, which the concrete factory's `get()` matches
     * too.
     */
    private const FACTORY = 'Drupal\Core\Logger\LoggerChannelFactoryInterface';

    /**
     * An assignment to a property of `$this` right before the call.
     */
    private const STORED = '/\$this\s*->\s*\w+\s*=\s*$/';

    /**
     * The end of the statement right after the call, so the channel itself
     * is stored and not something read off it.
     */
    private const STATEMENT_END = '/\G\s*;/';

    /**
     * How far back to look for the assignment.
     */
    private const LOOKBEHIND = 128;

    public function getTargets(): array
    {
        return [MethodTarget::exact(self::FACTORY, 'get')];
    }

    public function getRequirements(): array
    {
        return [FileAnalysisRequirement::SourceText];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        // Only a channel kept on the object is serialized, as phpstan-drupal
        // checks it.
        $span = $context->node->span;
        $contents = $context->source->contents;
        $from = max(0, $span->start - self::LOOKBEHIND);
        if (
            preg_match(self::STORED, substr($contents, $from, $span->start - $from)) !== 1
            || preg_match(self::STATEMENT_END, $contents, offset: $span->end) !== 1
        ) {
            return;
        }

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
