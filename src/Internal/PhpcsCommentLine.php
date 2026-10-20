<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function count;
use function str_contains;
use function trim;

/**
 * Where a phpcs comment sits on its line, which decides the lines it covers.
 *
 * Token walks are branchy by nature, as in `PhpTokens`.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class PhpcsCommentLine
{
    private function __construct() {}

    /**
     * Whether only whitespace comes before the token on its line, which is
     * how phpcs places an old-style `@codingStandards` comment. The stars and
     * the indentation of a docblock count as something else.
     *
     * @param list<array{int, string, int}> $tokens
     */
    public static function onOwnLine(array $tokens, int $index): bool
    {
        if ($index === 0) {
            return false;
        }

        $previous = $index - 1;
        while ($previous >= 0 && $tokens[$previous][0] === PhpcsTokens::WHITESPACE) {
            $previous--;
        }

        return $previous < 0 || $tokens[$previous][2] !== $tokens[$index][2];
    }

    /**
     * Whether other tokens, and other content, share the token's line, which
     * is how phpcs places a `phpcs:` comment. Whitespace never counts, and an
     * open tag or a docblock star before the token and a close tag after it
     * count only as other tokens.
     *
     * @param list<array{int, string, int}> $tokens
     *
     * @return array{bool, bool}
     */
    public static function lineContext(array $tokens, int $index, string $eol): array
    {
        $content = false;
        $others = false;
        if ($index === 0) {
            return [$content, $others];
        }

        $line = $tokens[$index][2];
        for ($previous = $index - 1; $previous > 0 && $tokens[$previous][2] === $line; $previous--) {
            [$kind, $text] = $tokens[$previous];
            if (self::isWhitespace($kind, $text)) {
                continue;
            }

            $others = true;
            $content = $kind !== PhpcsTokens::OPEN_TAG && $kind !== PhpcsTokens::DOC_STAR;
            if ($content) {
                break;
            }
        }

        // The token that ends the line is the last one looked at.
        $count = count($tokens);
        $changed = false;
        for ($next = $index; $next < $count && !$changed && !$content; $next++) {
            [$kind, $text] = $tokens[$next];
            $changed = str_contains($text, $eol);
            if ($next === $index || self::isWhitespace($kind, $text)) {
                continue;
            }

            $others = true;
            $content = $kind !== PhpcsTokens::CLOSE_TAG;
        }

        return [$content, $others];
    }

    /**
     * Whether phpcs skips the token when it looks for other content on a
     * comment's line.
     */
    private static function isWhitespace(int $kind, string $text): bool
    {
        return (
            $kind === PhpcsTokens::WHITESPACE
            || $kind === PhpcsTokens::DOC_WHITESPACE
            || $kind === PhpcsTokens::INLINE_HTML
            && trim($text) === ''
        );
    }
}
