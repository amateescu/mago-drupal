<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use PhpToken;

use function in_array;
use function strlen;
use function strtolower;
use function substr;

use const T_BOOLEAN_OR;
use const T_COALESCE;
use const T_ELSEIF;
use const T_IF;
use const T_LOGICAL_OR;
use const T_LOGICAL_XOR;
use const T_RETURN;
use const T_THROW;

/**
 * Says whether a check guards a later token.
 *
 * Three shapes count:
 *
 * - an `if` condition holds the check as one of its `&&` parts, and the
 *   token comes later in the condition or in the statement that the `if`
 *   guards;
 * - an `if` condition is the check negated with `!` and nothing else, its
 *   block ends in `throw` or `return`, and the token comes after the block,
 *   still inside the block that holds the `if`;
 * - a statement holds the check as one of its `&&` parts outside any
 *   parentheses, as in `$a = $check && f($b);`, and the token comes later in
 *   the same `&&` chain.
 *
 * Like the walks in PhpTokens, these walks are branchy by nature.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class IfGuards
{
    /**
     * Tokens that let a condition hold without each of its parts.
     */
    private const DISJUNCTIONS = [T_BOOLEAN_OR, T_LOGICAL_OR, T_LOGICAL_XOR, T_COALESCE];

    private function __construct() {}

    /**
     * Whether the check that starts at token `$check` and ends at byte
     * offset `$checkEnd` guards the token `$at`.
     *
     * @param list<PhpToken> $tokens
     */
    public static function guards(string $contents, array $tokens, int $check, int $checkEnd, int $at): bool
    {
        $before = PhpTokens::previous($tokens, $check - 1);
        $negated = $before !== null && $tokens[$before]->text === '!';
        $open = $negated ? PhpTokens::previous($tokens, $before - 1) : self::conditionOpen($tokens, $check);
        if (!$negated && $open === null) {
            return $before !== null && self::chainHoldsFor($tokens, $before, $checkEnd, $at);
        }

        $keyword = $open === null ? null : PhpTokens::previous($tokens, $open - 1);
        if (
            $open === null
            || $keyword === null
            || $tokens[$open]->text !== '('
            || !$tokens[$keyword]->is([T_IF, T_ELSEIF])
        ) {
            return false;
        }

        $close = PhpTokens::closer($tokens, $open, '(', ')');

        return $negated
            ? self::exitsBefore($contents, $tokens, $close, $checkEnd, $at)
            : self::holdsFor($tokens, $open, $close, $check, $at);
    }

    /**
     * Whether the condition between the parentheses at `$open` and `$close`
     * holds the check as one of its `&&` parts, and the token `$at` comes
     * later in it or in the statement it guards.
     *
     * @param list<PhpToken> $tokens
     */
    private static function holdsFor(array $tokens, int $open, int $close, int $check, int $at): bool
    {
        for ($i = $open + 1; $i < $close; $i++) {
            if (self::splits($tokens[$i])) {
                return false;
            }
        }

        if ($at > $check && $at < $close) {
            return true;
        }

        return $at > $close && $tokens[$at]->pos < PhpTokens::bodyEnd($tokens, $close + 1);
    }

    /**
     * Whether the check outside any parentheses, with the token `$before`
     * before it and ending at byte offset `$checkEnd`, is one `&&` part of
     * its statement, and the token `$at` comes later in the same `&&` chain.
     * A `||`, `or`, `xor`, `??` or `?` anywhere from the start of the
     * statement to the token cancels the guard, and so does a comma, a `=>`
     * or a `;` outside brackets between the check and the token.
     *
     * @param list<PhpToken> $tokens
     */
    private static function chainHoldsFor(array $tokens, int $before, int $checkEnd, int $at): bool
    {
        $end = PhpTokens::at($tokens, $checkEnd);
        $after = $end === null ? null : PhpTokens::next($tokens, $end);
        if (
            $after === null
            || $at <= $after
            || !in_array(strtolower($tokens[$after]->text), ['&&', 'and'], strict: true)
            || !in_array(strtolower($tokens[$before]->text), ['&&', 'and', '=', 'return'], strict: true)
        ) {
            return false;
        }

        for ($i = $before; $i >= 0 && !in_array($tokens[$i]->text, [';', '{', '}'], strict: true); $i--) {
            if (self::splits($tokens[$i])) {
                return false;
            }
        }

        $depth = 0;
        for ($i = $after; $i < $at; $i++) {
            $text = $tokens[$i]->text;
            $depth += match ($text) {
                '(', '[', '{', '${' => 1,
                ')', ']', '}' => -1,
                default => 0,
            };
            if (
                $depth < 0
                || self::splits($tokens[$i])
                || $depth === 0 && in_array($text, [',', '=>', ';'], strict: true)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the token lets an expression hold without each of its `&&`
     * parts.
     */
    private static function splits(PhpToken $token): bool
    {
        return $token->is(self::DISJUNCTIONS) || $token->text === '?';
    }

    /**
     * Whether the condition ending at `$close` is the negated check alone,
     * its block ends in `throw` or `return`, and the token `$at` comes after
     * the block, still inside the block that holds the `if`.
     *
     * @param list<PhpToken> $tokens
     */
    private static function exitsBefore(string $contents, array $tokens, int $close, int $checkEnd, int $at): bool
    {
        $last = PhpTokens::previous($tokens, $close - 1);
        $body = PhpTokens::next($tokens, $close + 1);
        if (
            $last === null
            || $body === null
            || ($tokens[$last]->pos + strlen($tokens[$last]->text)) !== $checkEnd
            || $tokens[$body]->text !== '{'
        ) {
            return false;
        }

        $end = PhpTokens::closer($tokens, $body, '{', '}');
        $after = $tokens[$end]->pos + 1;

        return (
            $at > $end
            && self::exits($tokens, $body, $end)
            && PhpTokens::staysInBlock(substr($contents, $after, $tokens[$at]->pos - $after))
        );
    }

    /**
     * Whether the last statement of the block between the braces at `$open`
     * and `$close` is a `throw` or a `return`.
     *
     * @param list<PhpToken> $tokens
     */
    private static function exits(array $tokens, int $open, int $close): bool
    {
        $semicolon = PhpTokens::previous($tokens, $close - 1);
        if ($semicolon === null || $tokens[$semicolon]->text !== ';') {
            return false;
        }

        $depth = 0;
        $i = $semicolon - 1;
        for (; $i > $open; $i--) {
            $text = $tokens[$i]->text;
            $depth += match ($text) {
                ')', ']', '}' => 1,
                '(', '[', '{' => -1,
                default => 0,
            };
            if ($depth === 0 && in_array($text, [';', '{', '}'], strict: true)) {
                break;
            }
        }

        $first = PhpTokens::next($tokens, $i + 1);

        return $first !== null && $tokens[$first]->is([T_THROW, T_RETURN]);
    }

    /**
     * The `(` that opens the condition holding the token at `$index`, found
     * by walking back over balanced parentheses. A statement boundary before
     * it means the token is in no condition.
     *
     * @param list<PhpToken> $tokens
     */
    private static function conditionOpen(array $tokens, int $index): ?int
    {
        $depth = 0;
        for ($i = $index - 1; $i >= 0; $i--) {
            $text = $tokens[$i]->text;
            if ($depth === 0 && $text === '(') {
                return $i;
            }

            if ($depth === 0 && in_array($text, [';', '{', '}'], strict: true)) {
                return null;
            }

            $depth += match ($text) {
                ')' => 1,
                '(' => -1,
                default => 0,
            };
        }

        return null;
    }
}
