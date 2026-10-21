<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Ignore;

use PhpToken;

use function array_key_last;
use function count;
use function explode;
use function ltrim;
use function preg_match;
use function rtrim;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strpos;
use function strspn;
use function strtolower;
use function substr;

use const T_CLOSE_TAG;
use const T_COMMENT;
use const T_DOC_COMMENT;
use const T_INLINE_HTML;
use const T_OPEN_TAG;
use const T_WHITESPACE;

/**
 * A file split into tokens the way PHP_CodeSniffer 3 splits it, as far as
 * its suppression comments need.
 *
 * phpcs gives each line of a multi-line token a token of its own, and splits
 * a docblock into its open tag, stars, tags, text and close tag. Each token
 * is its kind, its text and its line. The kinds that phpcs doesn't look at
 * near a comment are all `OTHER`.
 *
 * Token walks are branchy by nature, as in `PhpTokens`.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class PhpcsTokens
{
    public const OTHER = 0;

    public const WHITESPACE = 1;

    public const DOC_WHITESPACE = 2;

    public const INLINE_HTML = 3;

    public const OPEN_TAG = 4;

    public const CLOSE_TAG = 5;

    public const COMMENT = 6;

    public const DOC_OPEN = 7;

    public const DOC_STAR = 8;

    public const DOC_TAG = 9;

    public const DOC_STRING = 10;

    public const DOC_CLOSE = 11;

    /**
     * @var array<int, int>
     */
    private const KINDS = [
        T_WHITESPACE => self::WHITESPACE,
        T_INLINE_HTML => self::INLINE_HTML,
        T_OPEN_TAG => self::OPEN_TAG,
        T_CLOSE_TAG => self::CLOSE_TAG,
        T_COMMENT => self::COMMENT,
    ];

    private function __construct() {}

    /**
     * The line ending phpcs uses for the file: the first one in it, or `\n`
     * when it doesn't have any.
     */
    public static function lineEnding(string $contents): string
    {
        $matches = [];

        return preg_match("/\r\n?|\n/", $contents, $matches) === 1 ? $matches[0] : "\n";
    }

    /**
     * @return list<array{int, string, int}>
     */
    public static function of(string $contents): array
    {
        $eol = self::lineEnding($contents);
        $tokens = PhpToken::tokenize($contents);
        $pieces = [];
        $count = count($tokens);
        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];
            $text = $token->text;
            if (
                $token->id === T_DOC_COMMENT
                || $token->id === T_COMMENT && str_starts_with($text, '/**') && $text !== '/**/'
            ) {
                foreach (self::docblock($text, $eol) as $piece) {
                    $pieces[] = $piece;
                }

                continue;
            }

            if ($token->id === T_COMMENT && (str_starts_with($text, '//') || str_starts_with($text, '#'))) {
                $text .= self::takeNewline($tokens, $index + 1);
            }

            // The whitespace that gave its newline to a comment can be left
            // empty, and phpcs drops it, as it drops the empty piece after
            // the last line ending of a token.
            $lines = explode($eol, $text);
            $last = array_key_last($lines);
            foreach ($lines as $number => $line) {
                $line .= $number === $last ? '' : $eol;
                if ($line === '') {
                    continue;
                }

                $pieces[] = [self::KINDS[$token->id] ?? self::OTHER, $line, 0];
            }
        }

        return self::numbered($pieces, $eol);
    }

    /**
     * Moves the newline after a `//` or `#` comment into the comment, as
     * phpcs does on PHP 8, which puts it in the whitespace after the comment.
     *
     * @param list<PhpToken> $tokens
     */
    private static function takeNewline(array $tokens, int $index): string
    {
        $next = $tokens[$index] ?? null;
        if ($next === null || $next->id !== T_WHITESPACE) {
            return '';
        }

        foreach (["\r\n", "\n\r", "\n"] as $newline) {
            if (!str_starts_with($next->text, $newline)) {
                continue;
            }

            $next->text = substr($next->text, strlen($newline));

            return $newline;
        }

        return '';
    }

    /**
     * Sets the line of each token. A token that holds a line ending ends its
     * line.
     *
     * @param list<array{int, string, int}> $pieces
     *
     * @return list<array{int, string, int}>
     */
    private static function numbered(array $pieces, string $eol): array
    {
        $line = 1;
        foreach ($pieces as $index => $piece) {
            $pieces[$index][2] = $line;
            if (str_contains($piece[1], $eol)) {
                $line++;
            }
        }

        return $pieces;
    }

    /**
     * The tokens of a docblock, as phpcs's comment tokenizer makes them.
     *
     * @return list<array{int, string, int}>
     */
    private static function docblock(string $comment, string $eol): array
    {
        $length = strlen($comment);
        $open = $length - strlen(ltrim($comment, characters: '/*'));

        // A docblock of only slashes and stars keeps its last two as the
        // close tag.
        $bare = $open === $length && str_ends_with($comment, '*/');
        $tokens = [[self::DOC_OPEN, substr($comment, offset: 0, length: $bare ? -2 : $open), 0]];
        $body = $bare ? '*/' : ltrim($comment, characters: '/*');
        $text = rtrim($body, characters: '/*');
        $lines = explode($eol, $text);
        $last = array_key_last($lines);
        foreach ($lines as $number => $line) {
            $line .= $number === $last ? '' : $eol;
            $at = strspn($line, characters: " \t");
            if ($at > 0) {
                $tokens[] = [self::DOC_WHITESPACE, substr($line, offset: 0, length: $at), 0];
            }

            // Only the last line can be empty or end in whitespace.
            if ($line === '' || $at === strlen($line)) {
                break;
            }

            if ($number > 0 && $line[$at] === '*') {
                $tokens[] = [self::DOC_STAR, '*', 0];
                $at++;
            }

            foreach (self::docblockLine($line, $eol, $at) as $piece) {
                $tokens[] = $piece;
            }
        }

        $tokens[] = [self::DOC_CLOSE, substr($body, strlen($text)), 0];

        return $tokens;
    }

    /**
     * The tokens of one docblock line after its star.
     *
     * @mago-expect lint:halstead
     *
     * @return list<array{int, string, int}>
     */
    private static function docblockLine(string $line, string $eol, int $at): array
    {
        $tokens = [];
        $space = strspn($line, characters: " \t", offset: $at);
        if ($space > 0) {
            $tokens[] = [self::DOC_WHITESPACE, substr($line, $at, $space), 0];
            $at += $space;
        }

        if ($at >= strlen($line)) {
            return $tokens;
        }

        $tag = self::tag($line, $at);
        if ($tag !== null) {
            $tokens[] = [self::DOC_TAG, $tag, 0];
            $at += strlen($tag);
            $space = strspn($line, characters: " \t", offset: $at);
            if ($space > 0) {
                $tokens[] = [self::DOC_WHITESPACE, substr($line, $at, $space), 0];
                $at += $space;
            }
        }

        $newline = strpos($line, $eol, $at);
        $stop = $newline === false ? strlen($line) : $newline;
        if ($stop > $at) {
            $tokens[] = [self::DOC_STRING, substr($line, $at, $stop - $at), 0];
        }

        if ($newline !== false) {
            $tokens[] = [self::DOC_WHITESPACE, $eol, 0];
        }

        return $tokens;
    }

    /**
     * The tag that starts at the offset, or null. A `@phpcs:` comment is text
     * for phpcs, not a tag.
     */
    private static function tag(string $line, int $at): ?string
    {
        $matches = [];
        if ($line[$at] !== '@' || preg_match('/@\S+/', $line, $matches, offset: $at) !== 1) {
            return null;
        }

        return str_starts_with(strtolower($matches[0]), '@phpcs:') ? null : $matches[0];
    }
}
