<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Internal\DrupalFile;
use amateescu\MagoDrupal\Internal\FileMembers;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Analyzer\NodeAnalysisHook;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;

use function array_key_exists;
use function ltrim;
use function strtolower;

/**
 * Reports an anonymous class extending an `@internal` class of another
 * module.
 *
 * The host sends class-like hooks named classes only, so InternalParentHook
 * never sees `new class extends SomeInternalClass {}`. This hook reads the
 * parent off the `extends` clause and hands the rest to InternalParentHook.
 *
 * @internal
 */
final class AnonymousInternalParentHook implements NodeAnalysisHook
{
    /**
     * @param array<string, true> $internal Lowercased names of the classes
     *   marked `@internal` on disk.
     */
    public function __construct(
        private readonly array $internal,
    ) {}

    public function getTargets(): array
    {
        return [NodeKind::AnonymousClass];
    }

    public function getRequirements(): array
    {
        return [FileAnalysisRequirement::TargetSubtree];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        $file = $context->source;
        foreach ($file->getChildren($context->node) as $child) {
            if ($child->kind !== NodeKind::Extends) {
                continue;
            }

            $names = $file->getResolvedNames($child);
            $parent = $names === [] ? '' : ltrim($names[0]->name, characters: '\\');
            if ($parent !== '' && array_key_exists(strtolower($parent), $this->internal)) {
                InternalParentHook::report(
                    $context,
                    'An anonymous class',
                    self::owner($context),
                    $parent,
                    $child->span,
                );
            }

            return;
        }
    }

    /**
     * The module owning the anonymous class: the one owning the class around
     * it, or the module of a procedural file. Mago names an anonymous class
     * after its file, which names no module.
     */
    private static function owner(NodeAnalysisContext $context): ?string
    {
        $file = $context->source;
        $start = $context->node->span->start;
        // The offset before the node is outside the anonymous class and
        // inside the class around it, if there is one.
        $enclosing = $start === 0
            ? null
            : FileMembers::of($file)->classAt($context->codebase, $file, new Span($start - 1, $start));
        if ($enclosing !== null) {
            return InternalParentHook::owner($enclosing->originalName);
        }

        $drupalFile = DrupalFile::fromSource($file);

        return $drupalFile->isProcedural() ? strtolower($drupalFile->name) : null;
    }
}
