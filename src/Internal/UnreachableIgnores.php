<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function in_array;
use function substr;

/**
 * Finds the unreachable statements a PHPStan comment covers.
 *
 * PHPStan reports the first unreachable statement of a block, so the comment
 * that ignores it sits there. Mago reports every statement after it, and the
 * whole run goes with the comment, up to the brace that closes the block.
 *
 * @internal
 */
final class UnreachableIgnores
{
    private const IDENTIFIER = 'deadCode.unreachable';

    private function __construct() {}

    /**
     * Whether a comment ignoring unreachable code covers an earlier line in
     * the same block as the offset.
     */
    public static function cover(PHPStanIgnores $ignores, string $contents, int $offset): bool
    {
        foreach ($ignores->lines() as [, $end, $identifiers]) {
            // A line form ignores everything on its line, reachable or not,
            // so only the identifier says where a dead run starts.
            $unreachable = $identifiers !== true && in_array(self::IDENTIFIER, $identifiers, strict: true);
            if ($unreachable && $end < $offset && PhpTokens::staysInBlock(substr($contents, $end, $offset - $end))) {
                return true;
            }
        }

        return false;
    }
}
