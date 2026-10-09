<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

/**
 * Reads the arguments of an attribute.
 *
 * @internal
 */
final class AttributeArguments
{
    private function __construct() {}

    /**
     * Returns the value of the first positional argument, or of the argument
     * named $parameter, whichever the attribute has.
     *
     * @param Node|null $list The argument list node of the attribute.
     */
    public static function first(SourceFile $file, ?Node $list, string $parameter): ?Node
    {
        if ($list === null) {
            return null;
        }

        foreach ($file->getChildren($list) as $index => $argument) {
            $inner = $file->getChildren($argument)[0] ?? null;
            $parts = $inner === null ? [] : $file->getChildren($inner);
            $value = match (true) {
                $inner?->kind === NodeKind::PositionalArgument => $index === 0 ? $parts[0] ?? null : null,
                $inner?->kind === NodeKind::NamedArgument && $file->getText($parts[0]) === $parameter => $parts[1]
                    ?? null,
                default => null,
            };
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }
}
