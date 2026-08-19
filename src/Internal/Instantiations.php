<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function strspn;
use function strtolower;

/**
 * Reads the class name and arguments off a `new` expression.
 *
 * CallExpression only accepts call nodes, so instantiations need their own
 * accessor.
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
     * Returns the written class name read straight from the source text.
     *
     * NULL means undetermined, not absent: dynamic callees, anonymous
     * classes and any spelling the text scan cannot settle need name()
     * instead. A non-null result equals what name() returns for a directly
     * named class, so a caller can reject a candidate on it without
     * walking the callee.
     */
    public static function writtenNameFast(SourceFile $file, Node $node): ?string
    {
        $contents = $file->contents;
        // Skip the three bytes of `new`, then the whitespace run. A comment
        // there stops the scan at its slash, which falls out as NULL.
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
     * Only the callee is searched. The arguments hold identifiers too, so
     * searching the whole node returns `t` for `new RuntimeException(t(...))`.
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
     * Named and unpacked arguments are skipped, so an index here lines up with
     * the parameter position only when every earlier argument is positional.
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
