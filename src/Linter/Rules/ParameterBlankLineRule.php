<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Docblocks;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_filter;
use function array_map;
use function array_values;
use function in_array;
use function str_contains;
use function strpos;
use function substr;
use function trim;

/**
 * Reports a blank line in a multi-line function declaration.
 *
 * Ports Drupal.Functions.MultiLineFunctionDeclaration.EmptyLine. The check
 * runs on a function, method or closure whose parameter list spans lines,
 * and covers the list and the `use` list of a closure. It does not see
 * inside a default value that holds an array, a call, parentheses or a
 * string, inside an attribute, or inside a comment. An arrow function is
 * not checked. A fix removes the blank line.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class ParameterBlankLineRule implements Rule
{
    private const OPAQUE = [
        NodeKind::AttributeList,
        NodeKind::Array,
        NodeKind::LegacyArray,
        NodeKind::ArgumentList,
        NodeKind::Parenthesized,
        NodeKind::LiteralString,
        NodeKind::DocumentString,
        NodeKind::CompositeString,
        NodeKind::InterpolatedString,
    ];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/parameter-blank-line',
            name: 'Parameter blank line',
            description: 'Reports a blank line in a multi-line function declaration.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Function, NodeKind::Method, NodeKind::Closure],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $range = self::range($file, $context->node);
        if ($range === null) {
            return;
        }

        foreach (self::blankLines($file, $range[0], $range[1]) as $line) {
            $context->report(Issue::new(
                'Remove the blank line from the parameter list.',
                $line,
            )->withEdit(TextEdit::delete($line)));
        }
    }

    /**
     * Returns the parameter list and the offset where the checked text ends,
     * or null when the list is on one line. The text ends with the `use`
     * list of a closure, which counts only when the parameter list spans
     * lines.
     *
     * @return array{Node, int}|null
     */
    private static function range(SourceFile $file, Node $function): ?array
    {
        $children = $file->getChildren($function);
        $lists = array_values(array_filter(
            $children,
            static fn(Node $child): bool => $child->kind === NodeKind::FunctionLikeParameterList,
        ));
        $uses = array_values(array_filter(
            $children,
            static fn(Node $child): bool => $child->kind === NodeKind::ClosureUseClause,
        ));
        $list = $lists[0] ?? null;
        if ($list === null || !str_contains($file->getText($list), "\n")) {
            return null;
        }

        return [$list, ($uses[0] ?? $list)->span->end];
    }

    /**
     * Returns the span of each blank line, with its line break, between the
     * start of the list and $end.
     *
     * @return list<Span>
     */
    private static function blankLines(SourceFile $file, Node $list, int $end): array
    {
        $contents = $file->contents;
        $lines = [];
        $opaque = null;
        $newline = strpos($contents, needle: "\n", offset: $list->span->start);
        while ($newline !== false && $newline < $end) {
            $lineStart = $newline + 1;
            $newline = strpos($contents, needle: "\n", offset: $lineStart);
            if ($newline === false || $newline >= $end) {
                break;
            }

            if (trim(substr($contents, $lineStart, $newline - $lineStart)) !== '') {
                continue;
            }

            $opaque ??= self::opaqueSpans($file, $list);
            if (!self::inside($file, $opaque, $lineStart)) {
                $lines[] = new Span($lineStart, $newline + 1);
            }
        }

        return $lines;
    }

    /**
     * The spans in the parameter list that hold their own line breaks.
     *
     * @return list<Span>
     */
    private static function opaqueSpans(SourceFile $file, Node $list): array
    {
        return array_values(array_map(static fn(Node $node): Span => $node->span, array_filter(
            $file->getDescendants($list),
            static fn(Node $node): bool => in_array($node->kind, self::OPAQUE, strict: true),
        )));
    }

    /**
     * Whether the line that starts at $lineStart begins inside one of the
     * spans or inside a comment.
     *
     * @param list<Span> $spans
     */
    private static function inside(SourceFile $file, array $spans, int $lineStart): bool
    {
        foreach ($spans as $span) {
            if ($span->start < $lineStart && $span->end > $lineStart) {
                return true;
            }
        }

        $trivia = $file->getTrivia();
        $index = Docblocks::lastTriviaIndexStartingBefore($trivia, $lineStart - 1);

        return $index !== null && $trivia[$index]->span->end > $lineStart;
    }
}
