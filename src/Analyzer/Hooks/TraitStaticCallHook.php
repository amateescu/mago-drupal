<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Internal\DeclaredClass;
use amateescu\MagoDrupal\Internal\TraitDeclarations;
use amateescu\MagoDrupal\Internal\TraitRoots;
use amateescu\MagoDrupal\Internal\TraitStaticCalls;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\Metadata\ClassLikeKind;
use Mago\Sdk\Analyzer\Metadata\FunctionLikeMetadata;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Analyzer\NodeAnalysisHook;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

/**
 * Reports `self::name()` or `static::name()` in a static method of a trait
 * when a class using the trait declares the method non-static.
 *
 * TraitCallProvider types such a call from the classes' declarations. The
 * host memoizes provider answers by the call's types alone, so the provider
 * cannot tell a call in a static method, where PHP throws for an instance
 * method, from one in an instance method, where `$this` carries it. This hook
 * reads the trait's code and reports the first case, with PHP's wording.
 *
 * Mago reports a static call to an instance method only from outside the
 * class, so the same call in a class, or to a method the trait declares
 * itself, is not checked by anyone. This hook covers only the calls the
 * provider would otherwise type.
 *
 * @internal
 */
final class TraitStaticCallHook implements NodeAnalysisHook
{
    public const CODE = 'trait-non-static-call';

    public function __construct(
        private readonly TraitRoots $roots,
    ) {}

    public function getTargets(): array
    {
        return [NodeKind::Trait];
    }

    public function getRequirements(): array
    {
        return [FileAnalysisRequirement::TargetSubtree, FileAnalysisRequirement::SourceText];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        $file = $context->source;
        $calls = TraitStaticCalls::in($file, $context->node);
        if ($calls === []) {
            return;
        }

        $codebase = $context->codebase;
        $trait = DeclaredClass::resolve($file, $context->node, $codebase);
        if ($trait === null || $trait->kind !== ClassLikeKind::Trait) {
            return;
        }

        $roots = $this->roots->of($codebase, $trait->name);
        foreach ($calls as [$name, $call]) {
            $declared = $codebase->getMethod($trait->name, $name) === null
                ? self::instanceDeclaration(TraitDeclarations::of($codebase, $roots, $name) ?? [])
                : null;
            if ($declared === null) {
                continue;
            }

            $method = ($declared->identifier->class ?? '') . '::' . $declared->originalName;
            $context->report(
                Level::Error,
                self::CODE,
                Issue::new(
                    "Cannot call non-static method `{$method}` statically.",
                    $call->span,
                    'called from a static method of the trait',
                )->withHelp(
                    'A static method has no `$this`, so PHP throws here for every class that declares the method non-static.',
                ),
            );
        }
    }

    /**
     * The first of the declarations that is not static.
     *
     * @param list<FunctionLikeMetadata> $declarations
     */
    private static function instanceDeclaration(array $declarations): ?FunctionLikeMetadata
    {
        foreach ($declarations as $declared) {
            if (!$declared->static) {
                return $declared;
            }
        }

        return null;
    }
}
