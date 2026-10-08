<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Internal\DeprecatedSymbols;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Analyzer\NodeAnalysisHook;
use Mago\Sdk\Syntax\NodeKind;

use function in_array;
use function preg_match;
use function strtolower;

/**
 * Reports a class constant of a deprecated class-like.
 *
 * Mago reports a deprecated class constant, but not a constant of a
 * deprecated class-like, so every constant of
 * `DateTimeRangeConstantsInterface` goes unreported. The class is read off
 * the source text and its resolved name checked against the deprecated
 * class-likes, an imported alias included. A constant that is deprecated
 * itself is left to Mago's report, so the access is reported once.
 *
 * @internal
 */
final class DeprecatedConstantHook implements NodeAnalysisHook
{
    /**
     * Mago prefixes hook codes with the plugin identifier, so this is
     * reported as `drupal/deprecated-class`.
     */
    public const CODE = 'deprecated-class';

    /**
     * A class named by a name or a keyword, then the constant. Anything
     * else before `::`, such as `$object::X`, does not match.
     */
    private const ACCESS =
        '/\A(\\\\?[A-Za-z_][\w\\\\]*)' . DeprecatedUse::GAP . '::' . DeprecatedUse::GAP . '([A-Za-z_]\w*)\z/';

    /**
     * Prefixes that name the enclosing class or its parent. A class that
     * extends a deprecated class is reported where it extends it, and code
     * in a deprecated class is in a deprecation scope.
     */
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
        if (strtolower($constant) === 'class' || in_array(strtolower($prefix), self::KEYWORDS, strict: true)) {
            return;
        }

        $name = DeprecatedUse::className($context, $prefix, $span->start);
        $text = $name === null ? null : $this->symbols->classLike($name);
        if ($name === null || $text === null) {
            return;
        }

        $declared = $context->codebase->getClassConstant($name, $constant);
        if ($declared?->flags->contains(MetadataFlags::DEPRECATED) === true) {
            return;
        }

        $this->use->report(
            $context,
            self::CODE,
            $span,
            "Use of constant `{$constant}` of deprecated " . DeprecatedUse::describe($context->codebase, $name) . '.',
            $text,
        );
    }
}
