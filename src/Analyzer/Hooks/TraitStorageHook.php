<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Analyzer\Checks\EntityStorageInjectionCheck;
use amateescu\MagoDrupal\Analyzer\Checks\Reporter;
use amateescu\MagoDrupal\Internal\ClassFacts;
use amateescu\MagoDrupal\Internal\DeclaredClass;
use amateescu\MagoDrupal\Internal\StorageTypes;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\Metadata\ClassLikeKind;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Analyzer\NodeAnalysisHook;
use Mago\Sdk\Syntax\NodeKind;

use function array_keys;

/**
 * Runs the entity storage check on every trait, from metadata alone.
 *
 * A trait's property holds a storage in every class using it, and the class
 * checks see only the class's own properties. Like ClassMetadataHook, the
 * hook asks for no subtree and looks up only a trait that names a storage.
 *
 * @internal
 */
final class TraitStorageHook implements NodeAnalysisHook
{
    public function __construct(
        private readonly EntityStorageInjectionCheck $storage,
    ) {}

    public function getTargets(): array
    {
        return [NodeKind::Trait];
    }

    public function getRequirements(): array
    {
        return [FileAnalysisRequirement::SourceText];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        $file = $context->source;
        [$mentions, $candidates] = DeclaredClass::names($file, $context->node);

        /** @var list<non-empty-string> $names */
        $names = array_keys($mentions);
        if (!StorageTypes::namedLike($names) && !StorageTypes::documentedLike($file->getText($context->node->span))) {
            return;
        }

        $trait = DeclaredClass::resolve($file, $context->node, $context->codebase, $candidates);
        if ($trait === null || $trait->kind !== ClassLikeKind::Trait) {
            return;
        }

        $this->storage->check(new ClassFacts($trait, $context->codebase, $mentions), new Reporter($context));
    }
}
