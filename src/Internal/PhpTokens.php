<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use PhpToken;

use function count;
use function str_contains;
use function strlen;

use const T_CASE;
use const T_CURLY_OPEN;
use const T_DECLARE;
use const T_DEFAULT;
use const T_DOLLAR_OPEN_CURLY_BRACES;
use const T_ELSE;
use const T_ELSEIF;
use const T_ENDDECLARE;
use const T_ENDFOR;
use const T_ENDFOREACH;
use const T_ENDIF;
use const T_ENDSWITCH;
use const T_ENDWHILE;
use const T_FOR;
use const T_FOREACH;
use const T_IF;
use const T_SWITCH;
use const T_WHILE;

/**
 * Low-level moves over a tokenized PHP file.
 *
 * Token walks are branchy by nature; splitting them further would hide the
 * scan rather than simplify it.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class PhpTokens
{
    /**
     * The keywords that open an alternative syntax block when a colon
     * follows them or their condition.
     */
    private const ALTERNATIVE_OPENERS = [T_IF, T_FOREACH, T_FOR, T_WHILE, T_SWITCH, T_DECLARE];

    /**
     * The keywords that close an alternative syntax block.
     */
    private const ALTERNATIVE_ENDS = [T_ENDIF, T_ENDFOREACH, T_ENDFOR, T_ENDWHILE, T_ENDSWITCH, T_ENDDECLARE];

    private function __construct() {}

    /**
     * The tokens of an analyzed file, kept for the last file, so the hooks
     * and filters that read the same file's tokens tokenize it once.
     *
     * @return list<PhpToken>
     */
    public static function of(string $contents): array
    {
        return LastFile::get(self::class, $contents, static fn(): array => PhpToken::tokenize($contents));
    }

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
     * The index of the last token at or before `$from` that is not
     * whitespace or a comment.
     *
     * @param list<PhpToken> $tokens
     */
    public static function previous(array $tokens, int $from): ?int
    {
        for ($i = $from; $i >= 0; $i--) {
            if (!$tokens[$i]->isIgnorable()) {
                return $i;
            }
        }

        return null;
    }

    /**
     * The index of the token that holds the byte offset, or null past the
     * end. The tokens cover the file without gaps, in order.
     *
     * @param list<PhpToken> $tokens
     */
    public static function at(array $tokens, int $offset): ?int
    {
        $low = 0;
        $high = count($tokens) - 1;
        while ($low <= $high) {
            $middle = ($low + $high) >> 1;
            $token = $tokens[$middle];
            if ($offset < $token->pos) {
                $high = $middle - 1;
                continue;
            }

            if ($offset >= ($token->pos + strlen($token->text))) {
                $low = $middle + 1;
                continue;
            }

            return $middle;
        }

        return null;
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

    /**
     * Whether a piece of code stays inside the block it starts in.
     *
     * The code leaves its block when it closes more blocks than it opens. A
     * block is a pair of braces or an alternative syntax block, such as
     * `if (...):` up to `endif;`. An `else:` or `elseif (...):` of that syntax
     * and a `case` or `default` label of a `switch` at the starting level also
     * leave the block, since the code after them runs on another path.
     */
    public static function staysInBlock(string $code): bool
    {
        $tokens = PhpToken::tokenize('<?php ' . $code);
        $depth = 0;
        foreach ($tokens as $index => $token) {
            // A `case` or `default` label, or an alternative syntax `else` or
            // `elseif`, starts another branch of the block.
            if (
                $depth === 0
                && (
                    $token->is([T_CASE, T_DEFAULT])
                    || $token->is([T_ELSE, T_ELSEIF]) && self::takesColon($tokens, $index)
                )
            ) {
                return false;
            }

            $depth += match (true) {
                $token->text === '{', $token->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES]) => 1,
                $token->text === '}', $token->is(self::ALTERNATIVE_ENDS) => -1,
                $token->is(self::ALTERNATIVE_OPENERS) => self::takesColon($tokens, $index) ? 1 : 0,
                default => 0,
            };
            if ($depth < 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the control keyword at `$index` opens an alternative syntax
     * block: a colon follows the keyword or its parenthesized condition.
     *
     * @param list<PhpToken> $tokens
     */
    private static function takesColon(array $tokens, int $index): bool
    {
        $next = self::next($tokens, $index + 1);
        if ($next !== null && $tokens[$next]->text === '(') {
            $next = self::next($tokens, self::closer($tokens, $next, '(', ')') + 1);
        }

        return $next !== null && $tokens[$next]->text === ':';
    }
}
