<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function strspn;
use function strtolower;

/**
 * Reads the class name and the arguments from a `new` expression.
 *
 * CallExpression accepts only call nodes, so an instantiation must have its
 * own accessor.
 *
 * @internal
 */
final class Instantiations
{
    private const IDENTIFIER_KINDS = [
        NodeKind::LocalIdentifier,
        NodeKind::QualifiedIdentifier,
        NodeKind::FullyQualifiedIdentifier,
    ];

    private function __construct() {}

    /**
     * Returns the written class name, read directly from the source text.
     *
     * NULL means unknown, not absent. A dynamic callee, an anonymous class
     * and any spelling that the text scan cannot decide must go through
     * name() instead. A non-null result is equal to the result of name()
     * for a class with a direct name, so a caller can reject a candidate
     * on it without walking the callee.
     */
    public static function writtenNameFast(SourceFile $file, Node $node): ?string
    {
        $contents = $file->contents;
        // Skip the three bytes of `new`, then the whitespace run. A comment
        // there stops the scan at its slash, and the result is NULL.
        $offset = $node->span->start + 3;
        $offset += strspn($contents, characters: " \t\r\n\v\f", offset: $offset);

        $name = Calls::leadingIdentifier($contents, $offset);
        if ($name === null || strtolower($name) === 'class') {
            return null;
        }

        return $name;
    }

    /**
     * Returns the instantiated class name as written in the source.
     *
     * The search covers only the callee. The arguments also hold
     * identifiers, so searching the whole node returns `t` for
     * `new RuntimeException(t(...))`.
     */
    public static function name(SourceFile $file, Node $node): ?string
    {
        $callee = null;
        foreach ($file->getChildren($node) as $child) {
            if ($child->kind === NodeKind::Keyword || $child->kind === NodeKind::ArgumentList) {
                continue;
            }

            $callee = $child;

            break;
        }

        if ($callee === null) {
            return null;
        }

        foreach (self::IDENTIFIER_KINDS as $kind) {
            $identifier = $file->getFirstDescendant($callee, $kind);
            if ($identifier !== null) {
                return $file->getText($identifier);
            }
        }

        return null;
    }

    /**
     * Returns the positional argument values, in source order.
     *
     * This skips named and unpacked arguments, so an index here agrees
     * with the parameter position only if every earlier argument is
     * positional.
     *
     * @return list<Node>
     */
    public static function arguments(SourceFile $file, Node $node): array
    {
        $list = $file->getFirstDescendant($node, NodeKind::ArgumentList);
        if ($list === null) {
            return [];
        }

        $arguments = [];
        foreach ($file->getChildren($list) as $argument) {
            $variant = $file->getChildren($argument)[0] ?? null;
            if ($variant === null || $variant->kind !== NodeKind::PositionalArgument) {
                continue;
            }

            $value = $file->getChildren($variant)[0] ?? null;
            if ($value !== null) {
                $arguments[] = Values::unwrap($file, $value);
            }
        }

        return $arguments;
    }
}
