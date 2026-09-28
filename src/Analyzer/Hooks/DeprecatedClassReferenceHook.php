<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Internal\DeprecatedSymbols;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Analyzer\NodeAnalysisHook;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;

use function max;
use function preg_match;
use function strrpos;
use function substr;

/**
 * Reports a deprecated class-like named in a type declaration or a `catch`,
 * or called statically.
 *
 * Mago reports a deprecated class where it is instantiated, extended or used
 * as a trait. A parameter, return or property type naming one, a `catch` of
 * one, and a static call on one to a method that is not deprecated itself go
 * unreported. Only native types are read, not docblock types, and a call
 * through `parent::` is left to Mago's report on the `extends`. The short
 * name is checked against the class-likes marked on disk before the name is
 * resolved.
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
        return [FileAnalysisRequirement::SourceText, FileAnalysisRequirement::TargetSubtree];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        $node = $context->node;
        if ($node->kind === NodeKind::Hint) {
            $this->hint($context, $node);

            return;
        }

        $children = $context->source->getChildren($node);
        if ($children !== []) {
            $this->staticCall($context, $children[0], $children[1] ?? null);
        }
    }

    /**
     * Reports a type declaration naming a deprecated class-like.
     */
    private function hint(NodeAnalysisContext $context, Node $hint): void
    {
        $name = $this->deprecatedName($context, $hint);
        $text = $name === null ? null : $this->symbols->classLike($name);
        if ($name === null || $text === null) {
            return;
        }

        // The host targets the types of a `catch` as hints too.
        $from = max(0, $hint->span->start - self::LOOKBEHIND);
        $before = substr($context->source->contents, $from, $hint->span->start - $from);
        $subject = preg_match(self::CATCH, $before) === 1 ? 'Catch of' : 'Type declaration names';
        $this->use->report(
            $context,
            self::CODE,
            $hint->span,
            $subject . ' deprecated ' . DeprecatedUse::describe($context->codebase, $name) . '.',
            $text,
        );
    }

    /**
     * Reports a static call on a deprecated class-like, unless the method is
     * deprecated too, which Mago reports.
     */
    private function staticCall(NodeAnalysisContext $context, Node $class, ?Node $method): void
    {
        $name = $this->deprecatedName($context, $class);
        $text = $name === null ? null : $this->symbols->classLike($name);
        if ($name === null || $text === null || $method === null) {
            return;
        }

        $methodName = $context->source->getText($method);
        $declaring = $context->codebase->getDeclaringMethod($name, $methodName);
        if ($declaring?->flags->contains(MetadataFlags::DEPRECATED) === true) {
            return;
        }

        $this->use->report(
            $context,
            self::CODE,
            $context->node->span,
            "Call to static method `{$methodName}()` of deprecated "
            . DeprecatedUse::describe($context->codebase, $name)
            . '.',
            $text,
        );
    }

    /**
     * The resolved name the node spells, when its short name is one a
     * deprecated class-like has; null otherwise.
     */
    private function deprecatedName(NodeAnalysisContext $context, Node $node): ?string
    {
        $text = $context->source->getText($node);
        if (preg_match(self::NAME, $text) !== 1) {
            return null;
        }

        $short = substr($text, (int) strrpos('\\' . $text, needle: '\\'));

        return $this->symbols->hasShortName($short) ? DeprecatedUse::className($context, $node) : null;
    }
}
