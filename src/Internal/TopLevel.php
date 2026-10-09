<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function in_array;

/**
 * Tells what sits at the top level of a file, where it runs on every load
 * of the file.
 *
 * @internal
 */
final class TopLevel
{
    /**
     * Constructs that put a statement below the top level. A statement in one
     * of them runs on a condition or when something calls it.
     */
    private const NESTING = [
        NodeKind::Class_,
        NodeKind::Interface,
        NodeKind::Trait,
        NodeKind::Enum,
        NodeKind::AnonymousClass,
        NodeKind::Function,
        NodeKind::Closure,
        NodeKind::ArrowFunction,
        NodeKind::If,
        NodeKind::Switch,
        NodeKind::Try,
        NodeKind::For,
        NodeKind::Foreach,
        NodeKind::While,
        NodeKind::DoWhile,
        NodeKind::Declare,
    ];

    private function __construct() {}

    /**
     * Whether the node is below the top level of the file.
     *
     * A braced namespace nests its statements, and an unbraced one does not.
     * The snapshot must hold the node's ancestors.
     */
    public static function isNested(SourceFile $file, Node $node): bool
    {
        $parent = $file->getParent($node);
        while ($parent !== null) {
            if (in_array($parent->kind, self::NESTING, strict: true)) {
                return true;
            }

            if ($parent->kind === NodeKind::NamespaceBody && $file->contents[$parent->span->start] === '{') {
                return true;
            }

            $parent = $file->getParent($parent);
        }

        return false;
    }

    /**
     * The functions declared at the top level of a file. A function after
     * `namespace X;` counts, one inside `namespace X { }` does not.
     *
     * @return list<Node>
     */
    public static function functions(SourceFile $file): array
    {
        $functions = [];
        foreach ($file->getNodes(NodeKind::Function) as $function) {
            $statement = $file->getParent($function);
            $holder = $statement === null ? null : $file->getParent($statement);
            if (in_array($holder?->kind, [NodeKind::Program, NodeKind::NamespaceImplicitBody], strict: true)) {
                $functions[] = $function;
            }
        }

        return $functions;
    }
}
