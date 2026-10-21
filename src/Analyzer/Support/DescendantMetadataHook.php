<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Support;

use amateescu\MagoDrupal\Internal\Declaration\ClassFacts;
use amateescu\MagoDrupal\Internal\Declaration\DeclaredClass;
use amateescu\MagoDrupal\Internal\Text\CodeMask;
use Mago\Sdk\Analyzer\ClassLikeAnalysisHook;
use Mago\Sdk\Analyzer\ClassLikeTarget;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\Metadata\ClassLikeKind;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function max;
use function min;
use function preg_match;
use function preg_replace;
use function str_contains;
use function substr;

/**
 * Runs one check on every class descending from the given ancestors, from
 * metadata alone.
 *
 * The host resolves the ancestry and sends the class node's span. The class
 * itself comes from the codebase, and only once the class text passes the
 * check's text gate. One hook per check keeps the ancestor list per rule.
 *
 * @internal
 */
final class DescendantMetadataHook implements ClassLikeAnalysisHook
{
    /**
     * @param non-empty-list<non-empty-string> $ancestors
     */
    public function __construct(
        private readonly array $ancestors,
        private readonly MetadataCheck $check,
    ) {}

    public function getTargets(): array
    {
        $targets = [];
        foreach ($this->ancestors as $ancestor) {
            $targets[] = ClassLikeTarget::descendantsOf($ancestor);
        }

        return $targets;
    }

    public function getRequirements(): array
    {
        return $this->check->textGate() === null ? [] : [FileAnalysisRequirement::SourceText];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        // An interface extending the ancestor is a descendant too, but the
        // checks are about implementations.
        if ($context->node->kind !== NodeKind::Class_) {
            return;
        }

        // The gate reads the class without its comments, so code that a
        // comment holds, such as a commented-out property, doesn't count.
        $gate = $this->check->textGate();
        if ($gate !== null && preg_match($gate, self::code($context->source, $context->node->span)) !== 1) {
            return;
        }

        [$mentions, $candidates] = DeclaredClass::names($context->source, $context->node);
        $class = DeclaredClass::resolve($context->source, $context->node, $context->codebase, $candidates);
        if ($class === null || $class->kind !== ClassLikeKind::Class_) {
            return;
        }

        $this->check->check(new ClassFacts($class, $context->codebase, $mentions), new Reporter($context));
    }

    /**
     * The class text with its comments turned into spaces. The host sends
     * the comments' spans, so the file isn't tokenized again. A class with a
     * `?>` can hold text outside PHP, which the file's mask also blanks.
     */
    private static function code(SourceFile $source, Span $span): string
    {
        $contents = $source->contents;
        $text = $source->getText($span);
        if (str_contains($text, '?>')) {
            return substr(CodeMask::comments($contents), $span->start, $span->length());
        }

        $code = '';
        $at = $span->start;
        foreach ($source->getTrivia() as $trivia) {
            $from = max($trivia->span->start, $at);
            $to = min($trivia->span->end, $span->end);
            if ($from >= $to) {
                continue;
            }

            $comment = substr($contents, $from, $to - $from);
            $code .=
                substr($contents, $at, $from - $at)
                . (string) preg_replace('/[^\r\n]/', replacement: ' ', subject: $comment);
            $at = $to;
        }

        return $code . substr($contents, $at, $span->end - $at);
    }
}
