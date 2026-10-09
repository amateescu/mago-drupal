<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function count;

/**
 * Reads the elements of an array literal.
 *
 * @internal
 */
final class ArrayElements
{
    private function __construct() {}

    /**
     * Returns the key and value of each `key => value` element of an array
     * literal, unwrapped. An element without a key is left out.
     *
     * @return list<array{Node, Node}>
     */
    public static function pairs(SourceFile $file, Node $array): array
    {
        $pairs = [];
        foreach ($file->getChildren($array) as $element) {
            $pair = $element->kind === NodeKind::ArrayElement ? $file->getChildren($element)[0] ?? null : null;
            $parts = $pair?->kind === NodeKind::KeyValueArrayElement ? $file->getChildren($pair) : [];
            if (count($parts) < 2) {
                continue;
            }

            $pairs[] = [Values::unwrap($file, $parts[0]), Values::unwrap($file, $parts[1])];
        }

        return $pairs;
    }
}
