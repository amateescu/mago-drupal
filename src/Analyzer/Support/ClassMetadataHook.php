<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Support;

use amateescu\MagoDrupal\Internal\Declaration\ClassFacts;
use amateescu\MagoDrupal\Internal\Declaration\DeclaredClass;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\Metadata\ClassLikeKind;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Analyzer\NodeAnalysisHook;
use Mago\Sdk\Syntax\NodeKind;

/**
 * Runs the class-level checks that don't need the ancestry on every class, from
 * metadata alone.
 *
 * The hook asks for no subtree, so every class costs the host one node span.
 * The names resolved inside that span and the class text say which checks can
 * apply at all, and each check answers that for itself. Only a class that
 * some check applies to is looked up in the codebase.
 *
 * @internal
 */
final class ClassMetadataHook implements NodeAnalysisHook
{
    /**
     * @param list<MentionedCheck> $checks
     */
    public function __construct(
        private readonly array $checks,
    ) {}

    public function getTargets(): array
    {
        return [NodeKind::Class_];
    }

    public function getRequirements(): array
    {
        // Mago ships a file's text once for all hooks, and other hooks
        // already ask for it.
        return [FileAnalysisRequirement::SourceText];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        $file = $context->source;
        [$names, $candidates] = DeclaredClass::names($file, $context->node);
        $mentions = new ClassMentions($names, $candidates, $file, $context->node->span, $context->codebase);
        $checks = [];
        foreach ($this->checks as $check) {
            if (!$check->mentionedIn($mentions)) {
                continue;
            }

            $checks[] = $check;
        }

        if ($checks === []) {
            return;
        }

        $class = DeclaredClass::resolve($file, $context->node, $context->codebase, $candidates);
        if ($class === null || $class->kind !== ClassLikeKind::Class_) {
            return;
        }

        $facts = new ClassFacts($class, $context->codebase, $names);
        $reporter = new Reporter($context);
        foreach ($checks as $check) {
            $check->check($facts, $reporter);
        }
    }
}
