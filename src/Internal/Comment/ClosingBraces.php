<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Comment;

use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

/**
 * The closing braces that a comment can follow on the same line.
 *
 * @internal
 */
final class ClosingBraces
{
    private function __construct() {}

    /**
     * The offset of the `}` before the offset on the same line, with only
     * spaces or tabs between them, or null when there is none.
     */
    public static function before(string $contents, int $offset): ?int
    {
        $at = $offset - 1;
        while ($at >= 0 && ($contents[$at] === ' ' || $contents[$at] === "\t")) {
            --$at;
        }

        return $at >= 0 && $contents[$at] === '}' ? $at : null;
    }

    /**
     * The offsets of the closing braces of the classes, interfaces, traits
     * and enums of a file whose snapshot has the whole syntax tree. Coder
     * leaves anonymous classes out of its closing brace check, and so does
     * this list.
     *
     * @return array<int, true>
     */
    public static function ofClassLikes(SourceFile $file): array
    {
        $braces = [];
        foreach ([NodeKind::Class_, NodeKind::Interface, NodeKind::Trait, NodeKind::Enum] as $kind) {
            foreach ($file->getNodes($kind) as $node) {
                $braces[$node->span->end - 1] = true;
            }
        }

        return $braces;
    }
}
