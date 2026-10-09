<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

/**
 * Tells whether a docblock marks a declaration as deprecated.
 *
 * @internal
 */
final class DeprecatedDeclaration
{
    private function __construct() {}

    /**
     * Whether a docblock with a `@deprecated` tag is right above the
     * declaration. A deprecated constant stays until the next major release.
     *
     * An attribute list belongs to the declaration, and the docblock is
     * above it.
     */
    public static function isMarked(SourceFile $file, Node $node): bool
    {
        $declaration = $node;
        $first = $file->getChildren($node)[0] ?? null;
        if ($first !== null && $first->kind === NodeKind::AttributeList) {
            $declaration = $first;
        }

        $docblock = Docblocks::attachedTo($file, $declaration);
        if ($docblock === null) {
            return false;
        }

        foreach (Docblocks::tags($file, $docblock) as $tag) {
            // The tag name is the one case-sensitive part of the match, as in
            // Coder. The parsed name is lowercase.
            if ($file->getText($tag->nameSpan) === '@deprecated') {
                return true;
            }
        }

        return false;
    }
}
