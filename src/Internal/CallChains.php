<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_reverse;
use function in_array;

/**
 * Walks fluent method call chains such as `$a->b()->c()`.
 *
 * A method call node holds its receiver as the first child, wrapped in
 * `Expression` and, for a call, `Call` nodes. So the calls after one call sit
 * above it in the tree, and the calls before it and the value they start from
 * sit below it.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class CallChains
{
    /**
     * Nodes that only hold the expression below them.
     */
    private const WRAPPERS = [NodeKind::Expression, NodeKind::Literal, NodeKind::Call, NodeKind::Parenthesized];

    private function __construct() {}

    /**
     * The method calls made on the value the call returns, in order.
     *
     * @return list<Invocation>
     */
    public static function after(SourceFile $file, Node $call): array
    {
        $calls = [];
        $next = self::next($file, $call);
        while ($next !== null) {
            $calls[] = $next[1];
            $next = self::next($file, $next[0]);
        }

        return $calls;
    }

    /**
     * The value a chain starts from, and the method calls made on it, in
     * order. A value that is not a method call is its own start.
     *
     * @return array{Node, list<Invocation>}
     */
    public static function from(SourceFile $file, Node $expression): array
    {
        $calls = [];
        $node = self::unwrap($file, $expression);
        while ($node->kind === NodeKind::MethodCall) {
            $invocation = Invocation::fromNode($file, $node);
            $receiver = $file->getChildren($node)[0] ?? null;
            if ($invocation === null || $receiver === null) {
                break;
            }

            $calls[] = $invocation;
            $node = self::unwrap($file, $receiver);
        }

        return [$node, array_reverse($calls)];
    }

    /**
     * Whether the value the chain from the call ends with is dropped, by a
     * statement of its own, so no later code can call a method on it.
     */
    public static function dropped(SourceFile $file, Node $call): bool
    {
        $node = $call;
        for ($next = self::next($file, $node); $next !== null; $next = self::next($file, $node)) {
            $node = $next[0];
        }

        $parent = $file->getParent($node);
        while ($parent !== null && in_array($parent->kind, self::WRAPPERS, strict: true)) {
            $parent = $file->getParent($parent);
        }

        return $parent?->kind === NodeKind::ExpressionStatement;
    }

    /**
     * The method call made on the value of the node, or null when the value
     * goes anywhere else.
     *
     * @return array{Node, Invocation}|null
     */
    private static function next(SourceFile $file, Node $node): ?array
    {
        $parent = $file->getParent($node);
        while ($parent !== null && in_array($parent->kind, self::WRAPPERS, strict: true)) {
            $node = $parent;
            $parent = $file->getParent($parent);
        }

        if ($parent === null || $parent->kind !== NodeKind::MethodCall) {
            return null;
        }

        $invocation = ($file->getChildren($parent)[0] ?? null)?->id === $node->id
            ? Invocation::fromNode($file, $parent)
            : null;

        return $invocation === null ? null : [$parent, $invocation];
    }

    /**
     * Removes the expression, literal, call and parenthesis wrappers.
     */
    public static function unwrap(SourceFile $file, Node $node): Node
    {
        while (in_array($node->kind, self::WRAPPERS, strict: true)) {
            $child = $file->getChildren($node)[0] ?? null;
            if ($child === null) {
                break;
            }

            $node = $child;
        }

        return $node;
    }
}
