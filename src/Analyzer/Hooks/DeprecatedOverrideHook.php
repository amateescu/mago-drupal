<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Internal\Calls;
use amateescu\MagoDrupal\Internal\DeprecatedSymbols;
use amateescu\MagoDrupal\Internal\DeprecatedTag;
use amateescu\MagoDrupal\Internal\Types;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\MethodCallAnalysisHook;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\NodeAnalysisContext;

use function strtolower;

/**
 * Reports a call to a method that overrides a deprecated method without
 * saying so.
 *
 * Drupal deprecates an interface method on the interface and leaves the
 * implementation with `{@inheritdoc}`, as `ConfigEntityBase::trustData()`
 * does. Mago keeps the flag on the declaration that has the tag, so a call on
 * the concrete class goes unreported. PHPStan carries the tag over unless the
 * override says `@not-deprecated`, and so does this check. The host sends
 * only the calls to the methods marked on disk and their overrides; a method
 * Mago flags itself is left to Mago's `deprecated-method`.
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

            foreach (DeprecatedUse::lineage($codebase, $declaring) as $ancestor) {
                $text = $ancestor === strtolower($declaring) ? null : $this->symbols->method($ancestor, $name);
                if ($text === null) {
                    continue;
                }

                $this->use->report(
                    $context,
                    self::CODE,
                    $context->node->span,
                    'Call to `'
                    . DeprecatedUse::originalName($codebase, $declaring)
                    . "::{$method->originalName}()`, which overrides deprecated `"
                    . DeprecatedUse::originalName($codebase, $ancestor)
                    . "::{$method->originalName}()`.",
                    $text,
                );

                return;
            }
        }
    }
}
