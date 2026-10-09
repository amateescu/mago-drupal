<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function count;
use function max;
use function usort;

/**
 * The parts of a file that sit inside a body: a block, a class-like body, a
 * `match`, a `switch` or a body in the alternative syntax.
 *
 * A bare `{ }` block at the top level is no body, nor is a block inside
 * another such block. Coder reads these the same way.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class CodeBodies
{
    /**
     * Class-like kinds. Their body starts at the first `{` after the header.
     */
    private const CLASS_LIKE = [
        NodeKind::Class_,
        NodeKind::Interface,
        NodeKind::Trait,
        NodeKind::Enum,
        NodeKind::AnonymousClass,
    ];

    /**
     * Kinds whose whole span is a body.
     */
    private const BODIES = [
        NodeKind::SwitchBraceDelimitedBody,
        NodeKind::SwitchColonDelimitedBody,
        NodeKind::IfColonDelimitedBody,
        NodeKind::IfColonDelimitedBodyElseIfClause,
        NodeKind::IfColonDelimitedBodyElseClause,
        NodeKind::ForeachColonDelimitedBody,
        NodeKind::ForColonDelimitedBody,
        NodeKind::WhileColonDelimitedBody,
    ];

    /**
     * @param list<array{int, int}> $spans Sorted by start, with no overlap.
     */
    private function __construct(
        private readonly array $spans,
    ) {}

    /**
     * Reads the bodies of a file whose snapshot has the whole syntax tree.
     */
    public static function of(SourceFile $file): self
    {
        return new self(self::merge([
            ...self::braced($file, self::CLASS_LIKE),
            ...self::braced($file, [NodeKind::Match]),
            ...self::wholeSpans($file),
        ]));
    }

    /**
     * Whether the offset is inside a body.
     */
    public function contains(int $offset): bool
    {
        $low = 0;
        $high = count($this->spans) - 1;
        while ($low <= $high) {
            $middle = ($low + $high) >> 1;
            if ($this->spans[$middle][1] <= $offset) {
                $low = $middle + 1;

                continue;
            }

            if ($this->spans[$middle][0] > $offset) {
                $high = $middle - 1;

                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * The spans from the opening brace to the end of each node of the kinds.
     * A class-like body starts after its header and a `match` after its
     * subject.
     *
     * @param list<NodeKind> $kinds
     * @return list<array{int, int}>
     */
    private static function braced(SourceFile $file, array $kinds): array
    {
        $spans = [];
        foreach ($kinds as $kind) {
            foreach ($file->getNodes($kind) as $node) {
                $open = self::openBrace($file, $node, self::searchStart($file, $node));
                $spans = $open === null ? $spans : [...$spans, [$open, $node->span->end]];
            }
        }

        return $spans;
    }

    /**
     * The spans of the bodies that need no search for a brace: the blocks
     * that are not bare, and the bodies in the alternative syntax.
     *
     * @return list<array{int, int}>
     */
    private static function wholeSpans(SourceFile $file): array
    {
        $spans = [];
        foreach ([NodeKind::Block, ...self::BODIES] as $kind) {
            foreach ($file->getNodes($kind) as $node) {
                if ($kind === NodeKind::Block && self::isBare($file, $node)) {
                    continue;
                }

                $spans[] = [$node->span->start, $node->span->end];
            }
        }

        return $spans;
    }

    /**
     * @param list<array{int, int}> $spans
     * @return list<array{int, int}>
     */
    private static function merge(array $spans): array
    {
        usort($spans, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($spans as [$start, $end]) {
            $last = count($merged) - 1;
            if ($last < 0 || $start > $merged[$last][1]) {
                $merged[] = [$start, $end];

                continue;
            }

            $merged[$last][1] = max($merged[$last][1], $end);
        }

        return $merged;
    }

    /**
     * Whether a block stands alone at the top level of a file, or inside
     * another such block.
     */
    private static function isBare(SourceFile $file, Node $block): bool
    {
        $statement = $file->getParent($block);
        if ($statement === null || $statement->kind !== NodeKind::Statement) {
            return false;
        }

        $holder = $file->getParent($statement);

        if ($holder === null) {
            return false;
        }

        return match ($holder->kind) {
            NodeKind::Program, NodeKind::NamespaceImplicitBody => true,
            NodeKind::Block => self::isBare($file, $holder),
            default => false,
        };
    }

    /**
     * Where the search for a body's opening brace starts: after the header
     * of a class-like declaration, or after the subject of a `match`.
     */
    private static function searchStart(SourceFile $file, Node $node): int
    {
        $end = $node->span->start;
        foreach ($file->getChildren($node) as $child) {
            $isHeader = $node->kind === NodeKind::Match
                ? $child->kind === NodeKind::Expression && $end === $node->span->start
                : $child->kind !== NodeKind::ClassLikeMember;
            $end = $isHeader ? max($end, $child->span->end) : $end;
        }

        return $end;
    }

    /**
     * The offset of the first `{` in the code at or after $offset, within
     * the node.
     */
    private static function openBrace(SourceFile $file, Node $node, int $offset): ?int
    {
        $contents = $file->contents;
        while ($offset < $node->span->end) {
            $end = SourceText::commentEnd($contents, $offset);
            if ($end !== null) {
                $offset = $end;

                continue;
            }

            if ($contents[$offset] === '{') {
                return $offset;
            }

            ++$offset;
        }

        return null;
    }
}
