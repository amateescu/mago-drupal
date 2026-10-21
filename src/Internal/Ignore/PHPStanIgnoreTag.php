<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Ignore;

use amateescu\MagoDrupal\Internal\Text\PhpTokens;
use PhpToken;

use function preg_replace;
use function preg_split;
use function strlen;
use function strpos;
use function strrpos;

use const PREG_SPLIT_NO_EMPTY;

/**
 * The line one `@phpstan-ignore` tag with identifiers covers, with the
 * identifiers. `PHPStanLineForm` handles the line forms.
 *
 * @internal
 */
final class PHPStanIgnoreTag
{
    private function __construct() {}

    /**
     * The line one tag covers, with the identifiers it ignores there.
     *
     * @param list<PhpToken> $tokens
     * @return array{int, int, list<string>}|null
     */
    public static function covered(string $contents, array $tokens, int $index, string $ids): ?array
    {
        $identifiers = self::identifiers($ids);
        $target = self::codeBefore($tokens, $index) ? $tokens[$index]->pos : self::nextCode($tokens, $index);

        return $identifiers === [] || $target === null ? null : [...self::lineAround($contents, $target), $identifiers];
    }

    /**
     * The first and last offset of the line holding the offset.
     *
     * @return array{int, int}
     */
    public static function lineAround(string $contents, int $offset): array
    {
        $before = $offset === 0 ? false : strrpos($contents, needle: "\n", offset: $offset - strlen($contents) - 1);
        $after = strpos($contents, needle: "\n", offset: $offset);

        return [$before === false ? 0 : $before + 1, $after === false ? strlen($contents) : $after];
    }

    /**
     * The identifiers of a comma-separated list.
     *
     * @return list<string>
     */
    private static function identifiers(string $list): array
    {
        // The reasons in parentheses go first, then the list splits on commas.
        $bare = preg_replace('/[ \t]*\((?:[^()]|\([^()]*\))*\)/', replacement: '', subject: $list) ?? $list;
        $identifiers = preg_split('/[ \t]*,[ \t]*/', $bare, flags: PREG_SPLIT_NO_EMPTY);

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
}
