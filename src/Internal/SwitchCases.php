<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function count;
use function in_array;

/**
 * Reads the labels of a switch and the statements of its cases.
 *
 * @internal
 */
final class SwitchCases
{
    private const TERMINATORS = [
        NodeKind::Break,
        NodeKind::Continue,
        NodeKind::Return,
        NodeKind::Goto,
        NodeKind::Throw,
        NodeKind::ExitConstruct,
        NodeKind::DieConstruct,
    ];

    private const WRAPPERS = [NodeKind::ExpressionStatement, NodeKind::Expression, NodeKind::Construct];

    private function __construct() {}

    /**
     * Returns the `case` and `default` labels that belong to the switch
     * itself, in source order, for both the brace and the colon syntax.
     * Labels of a switch nested in a case body are not included.
     *
     * @return list<Node>
     */
    public static function labels(SourceFile $file, Node $switch): array
    {
        $children = $file->getChildren($switch);
        $body = $file->getChildren($children[count($children) - 1] ?? $switch)[0] ?? null;
        if ($body === null) {
            return [];
        }

        $labels = [];
        foreach ($file->getChildren($body) as $case) {
            if ($case->kind !== NodeKind::SwitchCase) {
                continue;
            }

            $label = $file->getChildren($case)[0] ?? null;
            if ($label === null) {
                continue;
            }

            $labels[] = $label;
        }

        return $labels;
    }

    /**
     * Returns the case's last statement when it ends the case with `break`,
     * `continue`, `return`, `throw`, `exit`, `die` or `goto`, or null.
     *
     * A case whose statements sit in a `{ }` block has no such statement.
     */
    public static function terminator(SourceFile $file, Node $case): ?Node
    {
        $children = $file->getChildren($case);
        $last = $children[count($children) - 1] ?? null;
        if ($last === null || $last->kind !== NodeKind::Statement) {
            return null;
        }

        return in_array(self::inner($file, $last)->kind, self::TERMINATORS, strict: true) ? $last : null;
    }

    /**
     * Returns what a statement holds: the `return` of a `return` statement,
     * the `throw` or `exit` of an expression statement, and so on. A `throw`,
     * `exit` and `die` are expressions that a statement wraps.
     */
    public static function inner(SourceFile $file, Node $statement): Node
    {
        $inner = $file->getChildren($statement)[0] ?? $statement;
        while (in_array($inner->kind, self::WRAPPERS, strict: true)) {
            $inner = $file->getChildren($inner)[0] ?? $statement;
        }

        return $inner;
    }

    /**
     * Whether the node is a statement that leaves its place with `break`,
     * `continue`, `return`, `throw`, `exit`, `die` or `goto`.
     */
    public static function isTerminator(SourceFile $file, Node $statement): bool
    {
        return in_array(self::inner($file, $statement)->kind, self::TERMINATORS, strict: true);
    }
}
