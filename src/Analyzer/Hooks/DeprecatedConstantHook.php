<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Internal\DeprecatedSymbols;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Analyzer\NodeAnalysisHook;
use Mago\Sdk\Syntax\NodeKind;

use function count;
use function strrpos;
use function strtolower;
use function substr;

/**
 * Reports a class constant that is deprecated or belongs to a deprecated
 * class-like.
 *
 * Mago reports deprecated global constants but no class constant, so
 * `FileSystemInterface::EXISTS_REPLACE` and every constant of
 * `DateTimeRangeConstantsInterface` go unreported. The constant's name and
 * the class's short name are checked against the symbols marked on disk
 * before anything is resolved. A constant is looked up on the class it is
 * read from and on that class's ancestors.
 *
 * @internal
 */
final class DeprecatedConstantHook implements NodeAnalysisHook
{
    /**
     * Mago prefixes hook codes with the plugin identifier, so these are
     * reported as `drupal/deprecated-class-constant` and
     * `drupal/deprecated-class`.
     */
    public const CODE = 'deprecated-class-constant';

    public const CLASS_CODE = 'deprecated-class';

    public function __construct(
        private readonly DeprecatedSymbols $symbols,
        private readonly DeprecatedUse $use,
    ) {}

    public function getTargets(): array
    {
        return [NodeKind::ClassConstantAccess];
    }

    public function getRequirements(): array
    {
        return [FileAnalysisRequirement::SourceText, FileAnalysisRequirement::TargetSubtree];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        $source = $context->source;
        $children = $source->getChildren($context->node);
        if (count($children) !== 2) {
            return;
        }

        [$class, $selector] = $children;
        $constant = $source->getText($selector);
        $prefix = $source->getText($class);
        $short = substr($prefix, (int) strrpos('\\' . $prefix, needle: '\\'));
        if (
            strtolower($constant) === 'class'
            || !$this->symbols->hasConstantName($constant) && !$this->symbols->hasShortName($short)
        ) {
            return;
        }

        $name = DeprecatedUse::className($context, $class);
        if ($name === null) {
            return;
        }

        foreach (DeprecatedUse::lineage($context->codebase, $name) as $ancestor) {
            $text = $this->symbols->constant($ancestor, $constant);
            if ($text !== null) {
                $this->use->report(
                    $context,
                    self::CODE,
                    $context->node->span,
                    'Use of deprecated constant `'
                    . DeprecatedUse::originalName($context->codebase, $ancestor)
                    . "::{$constant}`.",
                    $text,
                );

                return;
            }
        }

        $text = $this->symbols->classLike($name);
        if ($text !== null) {
            $this->use->report(
                $context,
                self::CLASS_CODE,
                $context->node->span,
                "Use of constant `{$constant}` of deprecated "
                . DeprecatedUse::describe($context->codebase, $name)
                . '.',
                $text,
            );
        }
    }
}
