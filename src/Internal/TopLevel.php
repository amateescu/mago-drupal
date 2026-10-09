<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function in_array;

/**
 * Finds the declarations that no block, class or function holds.
 *
 * @internal
 */
final class TopLevel
{
    private function __construct() {}

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
