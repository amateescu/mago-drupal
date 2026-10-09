<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function count;
use function trim;

/**
 * Reads the compile-time text of a string literal or a join of literals.
 *
 * @internal
 */
final class LiteralText
{
    private function __construct() {}

    /**
     * Returns the value of a string literal, or of literals joined with `.`.
     *
     * Any other part makes the result NULL. This is the value that PHP
     * builds at compile time, so `'a' . 'b'` and `'ab'` give the same result.
     */
    public static function of(SourceFile $file, Node $node): ?string
    {
        $node = Values::unwrap($file, $node);
        if ($node->kind === NodeKind::LiteralString) {
            return Values::literalString($file, $node);
        }

        if ($node->kind !== NodeKind::Binary) {
            return null;
        }

        $parts = $file->getChildren($node);
        if (count($parts) !== 3 || trim($file->getText($parts[1])) !== '.') {
            return null;
        }

        $left = self::of($file, $parts[0]);
        if ($left === null) {
            return null;
        }

        $right = self::of($file, $parts[2]);

        return $right === null ? null : $left . $right;
    }
}
