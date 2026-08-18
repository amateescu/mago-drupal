<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_pop;
use function trim;

/**
 * Reads values out of expression nodes.
 *
 * @internal
 */
final class Values
{
    private function __construct() {}

    /**
     * Returns the decoded value of a literal-string node.
     *
     * Falls back to trimming the quotes when the snapshot sends raw literals.
     */
    public static function literalString(SourceFile $file, Node $node): ?string
    {
        if ($node->kind !== NodeKind::LiteralString) {
            return null;
        }

        return $file->getLiteralString($node) ?? trim($file->getText($node), characters: '\'"');
    }

    /**
     * Unwraps the wrapper nodes around a value.
     *
     * An argument that is itself a call arrives as `Expression -> Call ->
     * FunctionCall`, so `Call` has to come off too or every check against a
     * call kind misses.
     */
    public static function unwrap(SourceFile $file, Node $node): Node
    {
        while (
            $node->kind === NodeKind::Expression
            || $node->kind === NodeKind::Literal
            || $node->kind === NodeKind::Call
        ) {
            $child = $file->getChildren($node)[0] ?? null;
            if ($child === null) {
                break;
            }

            $node = $child;
        }

        return $node;
    }

    /**
     * Whether the subtree concatenates strings with `.`.
     */
    public static function concatenates(SourceFile $file, Node $node): bool
    {
        // One walk with an early exit at the first '.' operator.
        $stack = [$node];
        while (($current = array_pop($stack)) !== null) {
            if ($current->kind === NodeKind::BinaryOperator && trim($file->getText($current)) === '.') {
                return true;
            }

            foreach ($file->getChildren($current) as $child) {
                $stack[] = $child;
            }
        }

        return false;
    }
}
