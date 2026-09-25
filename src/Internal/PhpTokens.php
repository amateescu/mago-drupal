<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use PhpToken;

use function count;
use function str_contains;

/**
 * Low-level moves over a tokenized PHP file.
 *
 * Token walks are branchy by nature; splitting them further would hide the
 * scan rather than simplify it.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class PhpTokens
{
    private function __construct() {}

    /**
     * The index of the token closing the delimiter opened at `$index`.
     *
     * The token at `$index` is the opener; an attribute's is `#[`, so it is
     * counted by position rather than by text.
     *
     * @param list<PhpToken> $tokens
     */
    public static function closer(array $tokens, int $index, string $open, string $close): int
    {
        $count = count($tokens);
        $depth = 1;
        for ($i = $index + 1; $i < $count; $i++) {
            $text = $tokens[$i]->text;
            if ($text === $open) {
                $depth++;
                continue;
            }

            if ($text !== $close) {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                return $i;
            }
        }

        return $count - 1;
    }

    /**
     * The offset just past a declaration's body, or past the `;` of one
     * without a body.
     *
     * @param list<PhpToken> $tokens
     */
    public static function bodyEnd(array $tokens, int $index): int
    {
        $count = count($tokens);
        $depth = 0;
        for ($i = $index; $i < $count; $i++) {
            $text = $tokens[$i]->text;
            if ($text === '(' || $text === '[') {
                $depth++;
                continue;
            }

            if ($text === ')' || $text === ']') {
                $depth--;
                continue;
            }

            if ($depth > 0) {
                continue;
            }

            if ($text === ';') {
                return $tokens[$i]->pos + 1;
            }

            if ($text === '{') {
                return $tokens[self::closer($tokens, $i, '{', '}')]->pos + 1;
            }
        }

        return $tokens[$count - 1]->pos;
    }

    /**
     * Whether any token from `$from` to `$to` holds the needle.
     *
     * @param list<PhpToken> $tokens
     */
    public static function mentions(array $tokens, int $from, int $to, string $needle): bool
    {
        for ($i = $from; $i <= $to; $i++) {
            if (str_contains($tokens[$i]->text, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The index of the next token that is not whitespace or a comment.
     *
     * @param list<PhpToken> $tokens
     */
    public static function next(array $tokens, int $from): ?int
    {
        $count = count($tokens);
        for ($i = $from; $i < $count; $i++) {
            if (!$tokens[$i]->isIgnorable()) {
                return $i;
            }
        }

        return null;
    }
}
