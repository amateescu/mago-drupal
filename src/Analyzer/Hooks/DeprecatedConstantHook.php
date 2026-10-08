<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Internal\DeprecatedSymbols;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Analyzer\NodeAnalysisHook;
use Mago\Sdk\Syntax\NodeKind;

use function in_array;
use function preg_match;
use function strtolower;

/**
 * Reports a class constant that is deprecated or belongs to a deprecated
 * class-like.
 *
 * Mago reports deprecated global constants but no class constant, so
 * `FileSystemInterface::EXISTS_REPLACE` and every constant of
 * `DateTimeRangeConstantsInterface` go unreported. The class and constant
 * are read off the source text: the constant's name is checked against the
 * ones marked on disk, and the class's resolved name against the deprecated
 * class-likes, an imported alias included. A constant is looked up on the
 * class it is read from and on that class's ancestors.
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

    /**
     * A class named by a name or a keyword, then the constant. Anything
     * else before `::`, such as `$object::X`, does not match.
     */
    private const ACCESS =
        '/\A(\\\\?[A-Za-z_][\w\\\\]*)' . DeprecatedUse::GAP . '::' . DeprecatedUse::GAP . '([A-Za-z_]\w*)\z/';

    private const KEYWORDS = ['self', 'static', 'parent'];

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
        return [FileAnalysisRequirement::SourceText];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        $span = $context->node->span;
        $matches = [];
        if (preg_match(self::ACCESS, $context->source->getText($span), $matches) !== 1) {
            return;
        }

        [, $prefix, $constant] = $matches;
        $deprecatedName = $this->symbols->hasConstantName($constant);
        if (
            strtolower($constant) === 'class'
            || !$deprecatedName && in_array(strtolower($prefix), self::KEYWORDS, strict: true)
        ) {
            return;
        }

        $name = DeprecatedUse::className($context, $prefix, $span->start);
        if ($name === null) {
            return;
        }

        foreach ($deprecatedName ? DeprecatedUse::lineage($context->codebase, $name) : [] as $ancestor) {
            $text = $this->symbols->constant($ancestor, $constant);
            if ($text !== null) {
                $this->use->report(
                    $context,
                    self::CODE,
                    $span,
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
                $span,
                "Use of constant `{$constant}` of deprecated "
                . DeprecatedUse::describe($context->codebase, $name)
                . '.',
                $text,
            );
        }
    }
}
