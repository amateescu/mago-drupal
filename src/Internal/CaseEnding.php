<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_filter;
use function array_values;
use function count;
use function ctype_digit;
use function in_array;
use function trim;

/**
 * Tells whether the statements of a switch case always leave the case.
 *
 * A statement leaves the case when it is a `break`, `continue`, `return`,
 * `throw`, `exit`, `die` or `goto`. Control flow statements leave it when every
 * branch does: an `if` with an `else`, a `try` and its `catch` blocks or
 * its `finally` block, and a nested `switch` with a `default` case.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class CaseEnding
{
    private const CLAUSES = [
        NodeKind::IfStatementBodyElseIfClause,
        NodeKind::IfStatementBodyElseClause,
        NodeKind::IfColonDelimitedBodyElseIfClause,
        NodeKind::IfColonDelimitedBodyElseClause,
    ];

    private const ELSE_CLAUSES = [NodeKind::IfStatementBodyElseClause, NodeKind::IfColonDelimitedBodyElseClause];

    private function __construct() {}

    /**
     * Whether one of the statements leaves the case, or the last one does
     * through its branches. Code after a statement that leaves the case
     * does not matter. A `{ }` block among the statements counts as part of
     * them.
     *
     * @param list<Node> $statements
     */
    public static function leaves(SourceFile $file, array $statements): bool
    {
        return self::hasTerminator($file, $statements) || self::listEnds($file, $statements, depth: 0);
    }

    /**
     * Whether a statement of the list is a terminator, or a block that holds
     * one.
     *
     * @param list<Node> $statements
     */
    private static function hasTerminator(SourceFile $file, array $statements): bool
    {
        foreach ($statements as $statement) {
            if (SwitchCases::isTerminator($file, $statement)) {
                return true;
            }

            $inner = SwitchCases::inner($file, $statement);
            if ($inner->kind === NodeKind::Block && self::hasTerminator($file, Nodes::statements($file, $inner))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the last of the statements always leaves the case. $depth
     * counts the nested `switch` statements around it, which a `break` or
     * `continue` of one level only leaves.
     *
     * @param list<Node> $statements
     */
    private static function listEnds(SourceFile $file, array $statements, int $depth): bool
    {
        $last = $statements[count($statements) - 1] ?? null;
        if ($last === null) {
            return false;
        }

        $inner = SwitchCases::inner($file, $last);

        return match ($inner->kind) {
            NodeKind::Break, NodeKind::Continue => self::level($file, $inner) > $depth,
            NodeKind::Return, NodeKind::Goto, NodeKind::Throw, NodeKind::ExitConstruct, NodeKind::DieConstruct => true,
            NodeKind::Block => self::listEnds($file, Nodes::statements($file, $inner), $depth),
            NodeKind::If => self::ifEnds($file, $inner, $depth),
            NodeKind::Try => self::tryEnds($file, $inner, $depth),
            NodeKind::Switch => self::switchEnds($file, $inner, $depth),
            default => false,
        };
    }

    /**
     * Whether an `if` has an `else` and every branch ends.
     */
    private static function ifEnds(SourceFile $file, Node $if, int $depth): bool
    {
        $bodies = array_values(array_filter(
            $file->getChildren($if),
            static fn(Node $child): bool => $child->kind === NodeKind::IfBody,
        ));
        $body = $file->getChildren($bodies[0] ?? $if)[0] ?? null;
        if ($bodies === [] || $body === null) {
            return false;
        }

        $clauses = array_filter($file->getChildren($body), static fn(Node $child): bool => in_array(
            $child->kind,
            self::CLAUSES,
            strict: true,
        ));
        $hasElse = array_filter($clauses, static fn(Node $clause): bool => in_array(
            $clause->kind,
            self::ELSE_CLAUSES,
            strict: true,
        )) !== [];
        $branches = [Nodes::statements($file, $body)];
        foreach ($clauses as $clause) {
            $branches[] = Nodes::statements($file, $clause);
        }

        foreach ($branches as $branch) {
            if (!self::listEnds($file, $branch, $depth)) {
                return false;
            }
        }

        return $hasElse;
    }

    /**
     * Whether a `finally` block ends, or the `try` block and every `catch`
     * block do.
     */
    private static function tryEnds(SourceFile $file, Node $try, int $depth): bool
    {
        $ends = true;
        foreach ($file->getChildren($try) as $child) {
            $block = $child->kind === NodeKind::Block ? $child : self::blockOf($file, $child);
            if ($block === null) {
                continue;
            }

            $blockEnds = self::listEnds($file, Nodes::statements($file, $block), $depth);
            if ($child->kind === NodeKind::TryFinallyClause) {
                if ($blockEnds) {
                    return true;
                }

                continue;
            }

            $ends = $ends && $blockEnds;
        }

        return $ends;
    }

    /**
     * The block of a `catch` or `finally` clause.
     */
    private static function blockOf(SourceFile $file, Node $clause): ?Node
    {
        foreach ($file->getChildren($clause) as $child) {
            if ($child->kind === NodeKind::Block) {
                return $child;
            }
        }

        return null;
    }

    /**
     * Whether a nested `switch` has a `default` case, and every case with
     * statements ends, the last one included.
     */
    private static function switchEnds(SourceFile $file, Node $switch, int $depth): bool
    {
        $children = $file->getChildren($switch);
        $body = $file->getChildren($children[count($children) - 1] ?? $switch)[0] ?? null;
        if ($body === null) {
            return false;
        }

        $labels = [];
        foreach ($file->getChildren($body) as $case) {
            $labels[] = $file->getChildren($case)[0] ?? $case;
        }

        $defaults = array_filter($labels, static fn(Node $label): bool => $label->kind === NodeKind::SwitchDefaultCase);
        $statements = [];
        foreach ($labels as $label) {
            $statements = Nodes::statements($file, $label);
            if ($statements !== [] && !self::listEnds($file, $statements, $depth + 1)) {
                return false;
            }
        }

        return $defaults !== [] && $statements !== [];
    }

    /**
     * The number of levels a `break` or `continue` leaves.
     */
    private static function level(SourceFile $file, Node $statement): int
    {
        foreach ($file->getChildren($statement) as $child) {
            $text = trim($file->getText($child));
            if ($child->kind === NodeKind::Expression && ctype_digit($text)) {
                return (int) $text;
            }
        }

        return 1;
    }
}
