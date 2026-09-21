<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_pop;
use function count;
use function in_array;
use function ltrim;
use function str_contains;
use function trim;

/**
 * Structural lookups on declaration nodes.
 *
 * @internal
 */
final class Nodes
{
    private function __construct() {}

    /**
     * The fully qualified name that a name node resolves to, without a
     * leading backslash. A name that is written fully qualified resolves
     * through its text.
     *
     * @return non-empty-string|null
     */
    public static function resolved(SourceFile $file, Node $node): ?string
    {
        $name = $file->getResolvedName($node)?->name;
        if ($name === null) {
            $text = trim($file->getText($node));

            return str_contains($text, '\\') ? Shape::nonEmptyString(ltrim($text, characters: '\\')) : null;
        }

        return Shape::nonEmptyString(ltrim($name, characters: '\\'));
    }

    /**
     * Returns the name identifier node of a declaration-like node.
     *
     * An attribute list is before the name and holds its own identifier,
     * so a plain descendant search returns `LegacyHook` for
     * `#[LegacyHook] function node_install()`. This method skips attribute
     * subtrees. The name identifier is before the parameter, extends and
     * value identifiers in child order, so the first hit is the declared
     * name. This also works for a node that nests the name, such as an enum
     * case.
     */
    public static function declaredIdentifier(SourceFile $file, Node $node): ?Node
    {
        $stack = [$node];
        while (($current = array_pop($stack)) !== null) {
            if ($current->kind === NodeKind::AttributeList) {
                continue;
            }

            if ($current->kind === NodeKind::LocalIdentifier) {
                return $current;
            }

            $children = $file->getChildren($current);
            for ($index = count($children) - 1; $index >= 0; --$index) {
                $stack[] = $children[$index];
            }
        }

        return null;
    }

    /**
     * Returns the declared name of a function, method or class-like node.
     */
    public static function declaredName(SourceFile $file, Node $node): ?string
    {
        $identifier = self::declaredIdentifier($file, $node);

        return $identifier === null ? null : $file->getText($identifier);
    }

    /**
     * Whether $node has an ancestor of one of the kinds below $root.
     *
     * A walked subtree can hold another node that the same rule targets,
     * such as a function inside a function. The worker dispatches the
     * nested target on its own, so the outer walk skips its contents and
     * does not report it twice.
     *
     * @param list<NodeKind> $kinds
     */
    public static function isNestedInside(SourceFile $file, Node $node, Node $root, array $kinds): bool
    {
        $parent = $file->getParent($node);
        while ($parent !== null && $parent->id !== $root->id) {
            if (in_array($parent->kind, $kinds, strict: true)) {
                return true;
            }

            $parent = $file->getParent($parent);
        }

        return false;
    }

    /**
     * Returns the body block of a function-like node.
     */
    public static function body(SourceFile $file, Node $node): ?Node
    {
        foreach ($file->getChildren($node) as $child) {
            if ($child->kind === NodeKind::Block || $child->kind === NodeKind::MethodBody) {
                return $child;
            }
        }

        return null;
    }
}
