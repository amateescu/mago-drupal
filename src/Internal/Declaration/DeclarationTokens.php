<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Declaration;

use amateescu\MagoDrupal\Internal\Text\PhpTokens;
use PhpToken;

use const T_ATTRIBUTE;
use const T_STRING;

/**
 * Reads the names a declaration's tokens spell.
 *
 * @internal
 */
final class DeclarationTokens
{
    private function __construct() {}

    /**
     * The name a `function` keyword at the index declares, or null for a
     * closure.
     *
     * @param list<PhpToken> $tokens
     */
    public static function functionName(array $tokens, int $index): ?string
    {
        $next = PhpTokens::next($tokens, $index + 1);
        if ($next !== null && $tokens[$next]->text === '&') {
            $next = PhpTokens::next($tokens, $next + 1);
        }

        return $next !== null && $tokens[$next]->is(T_STRING) ? $tokens[$next]->text : null;
    }

    /**
     * The index of the `#[` opening the attribute that ends at the index.
     *
     * @param list<PhpToken> $tokens
     */
    public static function attributeStart(array $tokens, int $index): int
    {
        $depth = 0;
        for ($i = $index; $i > 0; $i--) {
            $depth += match (true) {
                $tokens[$i]->text === ']' => 1,
                $tokens[$i]->text === '[', $tokens[$i]->is(T_ATTRIBUTE) => -1,
                default => 0,
            };
            if ($depth === 0) {
                return $i;
            }
        }

        return 0;
    }
}
