<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use PhpToken;

use function count;
use function in_array;

use const T_FUNCTION;
use const T_STRING;
use const T_VARIABLE;

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
     * The names a `const` statement declares: each name followed by `=`, so
     * a type before the first one is skipped.
     *
     * @param list<PhpToken> $tokens
     * @return list<string>
     */
    public static function constantNames(array $tokens, int $index): array
    {
        $names = [];
        $count = count($tokens);
        for ($i = $index + 1; $i < $count && $tokens[$i]->text !== ';'; $i++) {
            $next = PhpTokens::next($tokens, $i + 1);
            if ($tokens[$i]->is(T_STRING) && $next !== null && $tokens[$next]->text === '=') {
                $names[] = $tokens[$i]->text;
            }
        }

        return $names;
    }

    /**
     * The property a declaration starting at the index names, with its `$`,
     * or null when a function or the end of the statement comes first.
     *
     * @param list<PhpToken> $tokens
     */
    public static function propertyName(array $tokens, int $index): ?string
    {
        $count = count($tokens);
        for ($i = $index; $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token->is(T_VARIABLE)) {
                return $token->text;
            }

            if ($token->is(T_FUNCTION) || in_array($token->text, [';', '{', '(', '='], strict: true)) {
                return null;
            }
        }

        return null;
    }
}
