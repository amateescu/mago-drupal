<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function count;
use function in_array;

/**
 * Structural lookups on expression nodes in a complete syntax tree.
 *
 * @internal
 */
final class Expressions
{
    /**
     * Nodes that only wrap the expression inside them.
     */
    private const WRAPPERS = [NodeKind::Expression, NodeKind::Call, NodeKind::Variable];

    /**
     * The scopes a local variable lives in.
     */
    private const SCOPES = [NodeKind::Function, NodeKind::Method, NodeKind::Closure, NodeKind::ArrowFunction];

    private function __construct() {}

    /**
     * The node of one kind that covers exactly a span, such as the call a
     * method call hook was handed, looked up in the complete tree.
     */
    public static function at(SourceFile $file, NodeKind $kind, Span $span): ?Node
    {
        foreach ($file->getNodes($kind) as $node) {
            if ($node->span->start === $span->start && $node->span->end === $span->end) {
                return $node;
            }
        }

        return null;
    }

    /**
     * The outermost wrapper of a node, and the node that holds that wrapper.
     *
     * @return array{Node, Node|null}
     */
    public static function unwrap(SourceFile $file, Node $node): array
    {
        $parent = $file->getParent($node);
        while ($parent !== null && in_array($parent->kind, self::WRAPPERS, strict: true)) {
            $node = $parent;
            $parent = $file->getParent($node);
        }

        return [$node, $parent];
    }

    /**
     * The plain `$name` variable an expression consists of, if it is one.
     */
    public static function directVariable(SourceFile $file, Node $node): ?Node
    {
        while ($node->kind === NodeKind::Expression || $node->kind === NodeKind::Variable) {
            $children = $file->getChildren($node);
            if (count($children) !== 1) {
                return null;
            }

            $node = $children[0];
        }

        return $node->kind === NodeKind::DirectVariable ? $node : null;
    }

    /**
     * The function, method or closure a node runs in, or the file's root for
     * top-level code.
     */
    public static function scopeOf(SourceFile $file, Node $node): Node
    {
        $scope = $node;
        foreach ($file->getAncestors($node) as $ancestor) {
            $scope = $ancestor;
            if (in_array($ancestor->kind, self::SCOPES, strict: true)) {
                break;
            }
        }

        return $scope;
    }
}
