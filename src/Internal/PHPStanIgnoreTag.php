<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use PhpToken;

use function preg_split;
use function strlen;
use function strpos;
use function strrpos;

use const PREG_SPLIT_NO_EMPTY;

/**
 * The line one `@phpstan-ignore` tag covers, with what it ignores there.
 *
 * @internal
 */
final class PHPStanIgnoreTag
{
    private function __construct() {}

    /**
     * The line one tag covers, with what it ignores there.
     *
     * @param list<PhpToken> $tokens
     * @return array{int, int, list<string>|true}|null
     */
    public static function covered(string $contents, array $tokens, int $index, string $kind, string $ids): ?array
    {
        $comment = $tokens[$index];
        if ($kind === '-line') {
            return [...self::lineAround($contents, $comment->pos), true];
        }

        if ($kind === '-next-line') {
            // A `//` comment stops before its newline and a `/* */` one after
            // its last character, so the search starts on that character.
            $newline = strpos($contents, needle: "\n", offset: $comment->pos + strlen($comment->text) - 1);

            return $newline === false ? null : [...self::lineAround($contents, $newline + 1), true];
        }

        $identifiers = self::identifiers($ids);
        $target = self::codeBefore($tokens, $index) ? $comment->pos : self::nextCode($tokens, $index);

        return $identifiers === [] || $target === null ? null : [...self::lineAround($contents, $target), $identifiers];
    }

    /**
     * The identifiers of a comma-separated list.
     *
     * @return list<string>
     */
    private static function identifiers(string $list): array
    {
        $identifiers = preg_split('/[ \t]*,[ \t]*/', $list, flags: PREG_SPLIT_NO_EMPTY);

        return $identifiers === false ? [] : $identifiers;
    }

    /**
     * Whether code comes before the comment on its line.
     *
     * @param list<PhpToken> $tokens
     */
    private static function codeBefore(array $tokens, int $index): bool
    {
        $line = $tokens[$index]->line;
        for ($before = $index - 1; $before >= 0 && $tokens[$before]->line === $line; $before--) {
            if (!$tokens[$before]->isIgnorable()) {
                return true;
            }
        }

        return false;
    }

    /**
     * The offset of the next code after the comment.
     *
     * @param list<PhpToken> $tokens
     */
    private static function nextCode(array $tokens, int $index): ?int
    {
        $next = PhpTokens::next($tokens, $index + 1);

        return $next === null ? null : $tokens[$next]->pos;
    }

    /**
     * The first and last offset of the line holding the offset.
     *
     * @return array{int, int}
     */
    private static function lineAround(string $contents, int $offset): array
    {
        $before = $offset === 0 ? false : strrpos($contents, needle: "\n", offset: $offset - strlen($contents) - 1);
        $after = strpos($contents, needle: "\n", offset: $offset);

        return [$before === false ? 0 : $before + 1, $after === false ? strlen($contents) : $after];
    }
}
