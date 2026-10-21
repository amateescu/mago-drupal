<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use PhpToken;

use function in_array;
use function ltrim;
use function strtolower;
use function substr;

use const T_BOOLEAN_OR;
use const T_COALESCE;
use const T_ELSEIF;
use const T_IF;
use const T_IS_EQUAL;
use const T_IS_IDENTICAL;
use const T_IS_NOT_EQUAL;
use const T_IS_NOT_IDENTICAL;
use const T_LOGICAL_OR;
use const T_LOGICAL_XOR;
use const T_RETURN;
use const T_THROW;

/**
 * Says whether a check guards a later token.
 *
 * The check is true when it stands alone or is compared with `true`, as in
 * `$check === true`. It is false when it is negated with `!` or compared
 * with `false`, as in `$check === false` or `false == $check`. Three shapes
 * count:
 *
 * - an `if` or `elseif` condition holds the true check as one of its `&&`
 *   parts, and the token comes later in the condition or in the statement
 *   that the `if` guards;
 * - an `if` condition is the false check and nothing else, its block ends in
 *   `throw` or `return`, and the token comes after the block, still inside
 *   the block that holds the `if`. The `if` must start a statement, so an
 *   `elseif`, an `else if` or the body of another `if` doesn't count;
 * - a statement holds the true check as one of its `&&` parts outside any
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

    /**
     * The comparisons a check can be in, keyed by token id: true for the
     * ones that hold when both sides are equal, false for the ones that hold
     * when they differ.
     */
    private const COMPARISONS = [
        T_IS_IDENTICAL => true,
        T_IS_EQUAL => true,
        T_IS_NOT_IDENTICAL => false,
        T_IS_NOT_EQUAL => false,
    ];

    /**
     * The operators that join the `&&` parts of a condition.
     */
    private const ANDS = ['&&', 'and'];

    private function __construct() {}

    /**
     * Whether the check that starts at token `$check` and ends at byte
     * offset `$checkEnd` guards the token `$at`.
     *
     * @param list<PhpToken> $tokens
     */
    public static function guards(string $contents, array $tokens, int $check, int $checkEnd, int $at): bool
    {
        $end = PhpTokens::at($tokens, $checkEnd - 1);
        if ($end === null) {
            return false;
        }

        [$first, $last, $true] = self::guard($tokens, $check, $end);
        $before = PhpTokens::previous($tokens, $first - 1);
        $after = PhpTokens::next($tokens, $last + 1);
        if ($before === null || $after === null || $at <= $last) {
            return false;
        }

        if (!$true) {
            // An `if` that doesn't start a statement may not run at all.
            $keyword = self::keyword($tokens, $before);
            $previous = $keyword === null ? null : PhpTokens::previous($tokens, $keyword - 1);

            return (
                $keyword !== null
                && $tokens[$keyword]->is(T_IF)
                && ($previous === null || in_array($tokens[$previous]->text, [';', '{', '}'], strict: true))
                && PhpTokens::closer($tokens, $before, '(', ')') === $after
                && self::exitsBefore($contents, $tokens, $after, $at)
            );
        }

        $open = self::conditionOpen($tokens, $first);
        if ($open === null) {
            return self::chainHoldsFor($tokens, $before, $after, $at);
        }

        $keyword = self::keyword($tokens, $open);
        if (
            $keyword === null
            || !$tokens[$keyword]->is([T_IF, T_ELSEIF])
            || !in_array(strtolower($tokens[$before]->text), ['(', ...self::ANDS], strict: true)
            || !in_array(strtolower($tokens[$after]->text), [')', ...self::ANDS], strict: true)
        ) {
            return false;
        }

        return self::holdsFor($tokens, $open, PhpTokens::closer($tokens, $open, '(', ')'), $last, $at);
    }

    /**
     * The first and last token of the check with its `!` and its comparison
     * with a `true` or `false` literal, and whether that expression is true
     * when the check is.
     *
     * The check ends at the token `$end`. `!` binds tighter than a
     * comparison, so `!$check === false` is true when the check is.
     *
     * @param list<PhpToken> $tokens
     *
     * @return array{int, int, bool}
     */
    private static function guard(array $tokens, int $check, int $end): array
    {
        $first = $check;
        $true = true;
        $before = PhpTokens::previous($tokens, $check - 1);
        if ($before !== null && $tokens[$before]->text === '!') {
            $first = $before;
            $true = false;
        }

        // The comparison after the check, then the mirrored one before it.
        $after = PhpTokens::next($tokens, $end + 1);
        $previous = PhpTokens::previous($tokens, $first - 1);
        foreach ([
            [$after, $after === null ? null : PhpTokens::next($tokens, $after + 1)],
            [$previous, $previous === null ? null : PhpTokens::previous($tokens, $previous - 1)],
        ] as [$operator, $literal]) {
            if ($operator === null || $literal === null) {
                continue;
            }

            $equal = self::COMPARISONS[$tokens[$operator]->id] ?? null;
            $value = match (strtolower(ltrim($tokens[$literal]->text, characters: '\\'))) {
                'true' => true,
                'false' => false,
                default => null,
            };
            if ($equal !== null && $value !== null) {
                $holds = $true === ($equal === $value);

                return $operator === $after ? [$first, $literal, $holds] : [$literal, $end, $holds];
            }
        }

        return [$first, $end, $true];
    }

    /**
     * The index of the keyword before the `(` at `$open`, or null when the
     * token isn't a `(`.
     *
     * @param list<PhpToken> $tokens
     */
    private static function keyword(array $tokens, int $open): ?int
    {
        return $tokens[$open]->text === '(' ? PhpTokens::previous($tokens, $open - 1) : null;
    }

    /**
     * Whether the condition between the parentheses at `$open` and `$close`
     * doesn't have a `||`, `or`, `xor`, `??` or `?`, and the token `$at`
     * comes after the check, which ends at the token `$last`, in the
     * condition or in the statement it guards.
     *
     * @param list<PhpToken> $tokens
     */
    private static function holdsFor(array $tokens, int $open, int $close, int $last, int $at): bool
    {
        for ($i = $open + 1; $i < $close; $i++) {
            if (self::splits($tokens[$i])) {
                return false;
            }
        }

        if ($at > $last && $at < $close) {
            return true;
        }

        return $at > $close && $tokens[$at]->pos < PhpTokens::bodyEnd($tokens, $close + 1);
    }

    /**
     * Whether the check outside any parentheses, between the tokens
     * `$before` and `$after`, is one `&&` part of its statement, and the
     * token `$at` comes later in the same `&&` chain.
     * A `||`, `or`, `xor`, `??` or `?` anywhere from the start of the
     * statement to the token cancels the guard, and so does a comma, a `=>`
     * or a `;` outside brackets between the check and the token.
     *
     * @param list<PhpToken> $tokens
     */
    private static function chainHoldsFor(array $tokens, int $before, int $after, int $at): bool
    {
        if (
            $at <= $after
            || !in_array(strtolower($tokens[$after]->text), self::ANDS, strict: true)
            || !in_array(strtolower($tokens[$before]->text), [...self::ANDS, '=', 'return'], strict: true)
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
     * Whether the block of the `if` whose condition ends at `$close` ends in
     * `throw` or `return`, and the token `$at` comes after the block, still
     * inside the block that holds the `if`.
     *
     * @param list<PhpToken> $tokens
     */
    private static function exitsBefore(string $contents, array $tokens, int $close, int $at): bool
    {
        $body = PhpTokens::next($tokens, $close + 1);
        if ($body === null || $tokens[$body]->text !== '{') {
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
