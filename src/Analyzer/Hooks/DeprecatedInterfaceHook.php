<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Internal\ClassTargets;
use amateescu\MagoDrupal\Internal\DeclaredClass;
use amateescu\MagoDrupal\Internal\DeprecatedSymbols;
use Mago\Sdk\Analyzer\ClassLikeAnalysisHook;
use Mago\Sdk\Analyzer\ClassLikeTarget;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\Metadata\ClassLikeKind;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Syntax\NodeKind;

use function in_array;

/**
 * Reports a class or enum that implements a deprecated interface.
 *
 * Mago reports a deprecated parent class, and an interface extending a
 * deprecated interface, but not an `implements` of one. The host sends only
 * the descendants of the interfaces marked on disk, and each class or enum is
 * reported once, at its name, for every deprecated interface it lists
 * itself.
 *
 * @internal
 */
final class DeprecatedInterfaceHook implements ClassLikeAnalysisHook
{
    /**
     * Mago prefixes hook codes with the plugin identifier, so this is reported
     * as `drupal/deprecated-class`.
     */
    public const CODE = DeprecatedConstantHook::CLASS_CODE;

    private const DECLARATIONS = [NodeKind::Class_, NodeKind::Enum];

    /**
     * @param non-empty-list<non-empty-string> $interfaces The deprecated
     *   interfaces, each accepted by `ClassLikeTarget::descendantsOf()`.
     */
    private function __construct(
        private readonly array $interfaces,
        private readonly DeprecatedSymbols $symbols,
        private readonly DeprecatedUse $use,
    ) {}

    /**
     * The hook for the deprecated interfaces the SDK accepts as targets, or
     * null when there is none.
     */
    public static function of(DeprecatedSymbols $symbols, DeprecatedUse $use): ?self
    {
        // The names come off the tokens of user code, and one the SDK rejects
        // must not fail the whole registration.
        $interfaces = ClassTargets::accepted($symbols->interfaces());

        return $interfaces === [] ? null : new self($interfaces, $symbols, $use);
    }

    public function getTargets(): array
    {
        $targets = [];
        foreach ($this->interfaces as $interface) {
            $targets[] = ClassLikeTarget::descendantsOf($interface);
        }

        return $targets;
    }

    public function getRequirements(): array
    {
        return [FileAnalysisRequirement::SourceText];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        if (!in_array($context->node->kind, self::DECLARATIONS, strict: true)) {
            return;
        }

        $class = DeclaredClass::resolve($context->source, $context->node, $context->codebase);
        if ($class === null) {
            return;
        }

        $subject = $class->kind === ClassLikeKind::Enum
            ? "Enum `{$class->originalName}` implements"
            : "Class `{$class->originalName}` implements";
        $where = ($class->nameLocation ?? $class->location)->span;
        foreach ($class->directParentInterfaces as $interface) {
            $text = $this->symbols->classLike($interface);
            if ($text === null) {
                continue;
            }

            $this->use->report(
                $context,
                self::CODE,
                $where,
                $subject . ' deprecated ' . DeprecatedUse::describe($context->codebase, $interface) . '.',
                $text,
            );
        }
    }
}
