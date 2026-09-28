<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Internal\DeprecatedSymbols;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Analyzer\NodeAnalysisHook;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;

use function in_array;
use function ltrim;
use function max;
use function preg_match;
use function strlen;
use function strtolower;
use function substr;

/**
 * Reports a deprecated class-like named in a type declaration or a `catch`,
 * or called statically.
 *
 * Mago reports a deprecated class where it is instantiated, extended or used
 * as a trait. A parameter, return or property type naming one, a `catch` of
 * one, and a static call on one to a method that is not deprecated itself go
 * unreported. Only native types are read, not docblock types, and a call
 * through `parent::` is left to Mago's report on the `extends`. The name is
 * read off the source text and its resolved form looked up among the
 * class-likes marked on disk, so an imported alias counts too.
 *
 * @internal
 */
final class DeprecatedClassReferenceHook implements NodeAnalysisHook
{
    /**
     * Mago prefixes hook codes with the plugin identifier, so this is reported
     * as `drupal/deprecated-class`.
     */
    public const CODE = DeprecatedConstantHook::CLASS_CODE;

    /**
     * A class name as written: no nullable, union or intersection mark.
     */
    private const NAME = '/^\\\\?[A-Za-z_][\w\\\\]*$/';

    /**
     * A class name, `::` and a method name at the start of a static call.
     */
    private const STATIC_CALL =
        '/\G(\\\\?[A-Za-z_][\w\\\\]*)'
            . DeprecatedUse::GAP
            . '::'
            . DeprecatedUse::GAP
            . '([A-Za-z_]\w*)'
            . DeprecatedUse::GAP
            . '\(/';

    /**
     * A call through these is left to Mago's report on the `extends`.
     */
    private const KEYWORDS = ['self', 'static', 'parent'];

    /**
     * How far back to look for a `catch` before a type.
     */
    private const LOOKBEHIND = 256;

    /**
     * The text before a type in a `catch`, other alternatives included.
     */
    private const CATCH = '/\bcatch\s*\(\s*(?:[\w\\\\]+\s*\|\s*)*$/i';

    public function __construct(
        private readonly DeprecatedSymbols $symbols,
        private readonly DeprecatedUse $use,
    ) {}

    public function getTargets(): array
    {
        return [NodeKind::Hint, NodeKind::StaticMethodCall];
    }

    public function getRequirements(): array
    {
        return [FileAnalysisRequirement::SourceText];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        if ($context->node->kind === NodeKind::Hint) {
            $this->hint($context);

            return;
        }

        $this->staticCall($context);
    }

    /**
     * Reports a type declaration naming a deprecated class-like.
     */
    private function hint(NodeAnalysisContext $context): void
    {
        $span = $context->node->span;
        $source = $context->source;
        $resolved = preg_match(self::NAME, $source->getText($span)) === 1 ? $source->getResolvedName($span) : null;
        $name = $resolved === null ? null : ltrim($resolved->name, characters: '\\');
        $text = $name === null ? null : $this->symbols->classLike($name);
        if ($name === null || $text === null) {
            return;
        }

        // The host targets the types of a `catch` as hints too.
        $from = max(0, $span->start - self::LOOKBEHIND);
        $before = substr($source->contents, $from, $span->start - $from);
        $subject = preg_match(self::CATCH, $before) === 1 ? 'Catch of' : 'Type declaration names';
        $this->use->report(
            $context,
            self::CODE,
            $span,
            $subject . ' deprecated ' . DeprecatedUse::describe($context->codebase, $name) . '.',
            $text,
        );
    }

    /**
     * Reports a static call on a deprecated class-like, unless the method is
     * deprecated too, which Mago reports.
     */
    private function staticCall(NodeAnalysisContext $context): void
    {
        $span = $context->node->span;
        $matches = [];
        if (preg_match(self::STATIC_CALL, $context->source->contents, $matches, offset: $span->start) !== 1) {
            return;
        }

        [, $prefix, $method] = $matches;
        $resolved = in_array(strtolower($prefix), self::KEYWORDS, strict: true)
            ? null
            : $context->source->getResolvedName(new Span($span->start, $span->start + strlen($prefix)));
        $name = $resolved === null ? null : ltrim($resolved->name, characters: '\\');
        $text = $name === null ? null : $this->symbols->classLike($name);
        if ($name === null || $text === null) {
            return;
        }

        $declaring = $context->codebase->getDeclaringMethod($name, $method);
        if ($declaring?->flags->contains(MetadataFlags::DEPRECATED) === true) {
            return;
        }

        $this->use->report(
            $context,
            self::CODE,
            $span,
            "Call to static method `{$method}()` of deprecated "
            . DeprecatedUse::describe($context->codebase, $name)
            . '.',
            $text,
        );
    }
}
