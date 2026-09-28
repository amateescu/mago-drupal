<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Internal\Calls;
use amateescu\MagoDrupal\Internal\DeprecatedSymbols;
use amateescu\MagoDrupal\Internal\DeprecatedTag;
use amateescu\MagoDrupal\Internal\Types;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\MethodCallAnalysisHook;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\NodeAnalysisContext;

use function strtolower;

/**
 * Reports a call to a method that implements a deprecated interface method
 * without saying so.
 *
 * Drupal deprecates an interface method on the interface and leaves the
 * implementation with `{@inheritdoc}`, as `ConfigEntityBase::trustData()`
 * does. Mago keeps the flag on the declaration that has the tag, so a call on
 * the concrete class goes unreported. PHPStan carries the tag over unless the
 * implementation says `@not-deprecated`, and so does this check. The host
 * sends only the calls to the interface methods marked on disk and their
 * implementations; a method Mago flags itself is left to Mago's
 * `deprecated-method`. The interface is looked for above the receiver's
 * class, so an implementation a trait provides counts too.
 *
 * Only interface methods are targets: the host checks every method call
 * against every target's class before its name, so each target costs time
 * on every call, and Drupal deprecates through interfaces.
 *
 * @internal
 */
final class DeprecatedOverrideHook implements MethodCallAnalysisHook
{
    /**
     * Mago prefixes hook codes with the plugin identifier, so this is reported
     * as `drupal/deprecated-method`.
     */
    public const CODE = 'deprecated-method';

    /**
     * @param non-empty-list<array{non-empty-string, non-empty-string}> $methods
     *   The deprecated methods as class and name, each accepted by
     *   `MethodTarget::exact()`.
     */
    private function __construct(
        private readonly array $methods,
        private readonly DeprecatedSymbols $symbols,
        private readonly DeprecatedUse $use,
    ) {}

    /**
     * The hook for the deprecated methods, or null when there is none.
     */
    public static function of(DeprecatedSymbols $symbols, DeprecatedUse $use): ?self
    {
        $methods = $symbols->methods();

        return $methods === [] ? null : new self($methods, $symbols, $use);
    }

    public function getTargets(): array
    {
        $targets = [];
        foreach ($this->methods as [$class, $method]) {
            $targets[] = MethodTarget::exact($class, $method);
        }

        return $targets;
    }

    public function getRequirements(): array
    {
        return [
            FileAnalysisRequirement::ReceiverType,
            FileAnalysisRequirement::TargetSubtree,
            FileAnalysisRequirement::SourceText,
        ];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        $name = Calls::name($context->source, $context->node);
        if ($name === null) {
            return;
        }

        $codebase = $context->codebase;
        foreach (Types::names($context->receiverType) as $class) {
            $method = $codebase->getDeclaringMethod($class, $name);
            $declaring = $method?->identifier->class;
            if (
                $method === null
                || $declaring === null
                || $method->flags->contains(MetadataFlags::DEPRECATED)
                || DeprecatedTag::optsOut($method)
            ) {
                continue;
            }

            foreach (DeprecatedUse::lineage($codebase, $class) as $ancestor) {
                $text = $this->symbols->method($ancestor, $name);
                if ($text === null) {
                    continue;
                }

                $this->use->report(
                    $context,
                    self::CODE,
                    $context->node->span,
                    self::message($codebase, $declaring, $ancestor, $method->originalName),
                    $text,
                );

                return;
            }
        }
    }

    /**
     * The message: the method called, and the declaration that deprecates
     * it when that is another one. A stub that restates a deprecated
     * interface method without its docblock leaves Mago no flag, and then the
     * two are the same.
     */
    private static function message(Codebase $codebase, string $declaring, string $deprecating, string $method): string
    {
        $called = DeprecatedUse::originalName($codebase, $declaring) . "::{$method}()";
        if (strtolower($declaring) === strtolower($deprecating)) {
            return "Call to deprecated method `{$called}`.";
        }

        return (
            "Call to `{$called}`, which implements deprecated `"
            . DeprecatedUse::originalName($codebase, $deprecating)
            . "::{$method}()`."
        );
    }
}
