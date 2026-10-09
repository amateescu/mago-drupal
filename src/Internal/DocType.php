<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function in_array;
use function ltrim;
use function preg_match;
use function preg_match_all;
use function rtrim;
use function str_contains;
use function strlen;
use function strspn;
use function substr;
use function trim;

use const PREG_OFFSET_CAPTURE;

/**
 * Reads phpDoc types, which may hold spaces inside brackets and quotes.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class DocType
{
    /**
     * A variable name at the start of the text.
     */
    public const VARIABLE = '/^\$[A-Za-z_\x80-\xff][\w\x80-\xff]*/';

    private const OPENING = '<{([';

    private const CLOSING = '>})]';

    private const WHITESPACE = " \t\n\r\v\f";

    /**
     * A name in a type, such as `Foo`, `\Foo\Bar` or `non-empty-string`.
     * Literal strings, variables and the constant after `::` match the
     * first branch and are skipped. A name followed by `:` or `?:` is an
     * array shape key and does not match.
     */
    private const NAME = '/
        (?:
          \'(?:[^\'\\\\]|\\\\.)*+\'
          | "(?:[^"\\\\]|\\\\.)*+"
          | \$[\w\x80-\xff]*+
          | ::[\w\x80-\xff*]*+
        )(*SKIP)(*FAIL)
        | (?<![\w\x80-\xff\\\\])
          \\\\?[A-Za-z_\x80-\xff][\w\x80-\xff]*+
          (?:[-\\\\][A-Za-z_\x80-\xff][\w\x80-\xff]*+)*+
          (?!\??:(?!:))
        /x';

    private function __construct() {}

    /**
     * The type at the start of a tag's content, or null when the content
     * starts with a variable instead. A type such as `array<string, int>`
     * ends at the first whitespace outside its brackets.
     */
    public static function leading(string $content): ?string
    {
        if ($content === '' || $content[0] === '$' || $content[0] === '&') {
            return null;
        }

        $depth = 0;
        $length = strlen($content);
        for ($i = 0; $i < $length; $i++) {
            $character = $content[$i];
            $i = self::quoteEnd($content, $i);
            $depth += self::depthChange($character);
            if ($depth <= 0 && str_contains(self::WHITESPACE, $character)) {
                return substr($content, offset: 0, length: $i);
            }
        }

        return $content;
    }

    /**
     * The tag content with the type moved before a variable name written
     * first, as in `$items array` to `array $items`, or null when no whole
     * type follows the name.
     */
    public static function typeFirst(string $content): ?string
    {
        $name = [];
        if (preg_match(self::VARIABLE, $content, $name) !== 1) {
            return null;
        }

        $after = substr($content, strlen($name[0]));
        $gap = strspn($after, characters: " \t");
        if ($gap === 0) {
            return null;
        }

        $remainder = substr($after, $gap);
        $type = self::leading($remainder);
        $next = ltrim(substr($remainder, $type === null ? 0 : strlen($type)));
        // A type with a space before `|`, `&` or `:` would be cut in two.
        if ($type === null || !self::whole($type) || in_array($next[0] ?? '', ['|', '&', ':'], strict: true)) {
            return null;
        }

        return rtrim($type . ' ' . $name[0] . substr($remainder, strlen($type)));
    }

    /**
     * Whether the text is a whole type: balanced, and not cut short after a
     * union, intersection, return-type or list separator.
     */
    public static function whole(string $type): bool
    {
        return (
            $type[0] !== '$'
            && self::balanced($type)
            && !in_array(substr($type, offset: -1), ['|', '&', ':', ','], strict: true)
        );
    }

    /**
     * The members of a union, split at the `|` outside brackets.
     *
     * @return list<string>
     */
    public static function members(string $type): array
    {
        $members = [];
        $depth = 0;
        $start = 0;
        $length = strlen($type);
        for ($i = 0; $i < $length; $i++) {
            $character = $type[$i];
            $i = self::quoteEnd($type, $i);
            $depth += self::depthChange($character);
            if ($character === '|' && $depth === 0) {
                $members[] = trim(substr($type, $start, $i - $start));
                $start = $i + 1;
            }
        }

        $members[] = trim(substr($type, $start));

        return $members;
    }

    /**
     * Every name in the type with its offset, including the names inside
     * generic arguments, array shapes and callable signatures. A qualified
     * or hyphenated name comes out whole, so `\Foo\Bar` and `class-string`
     * are one name each.
     *
     * @return list<array{string, int}>
     */
    public static function names(string $type): array
    {
        $matches = [];
        preg_match_all(self::NAME, $type, $matches, flags: PREG_OFFSET_CAPTURE);
        // The stub for preg_match_all() does not model the offset-capture
        // shape. In that shape each match is a value and byte offset pair.
        // @mago-expect analysis:invalid-return-statement
        return $matches[0];
    }

    /**
     * Whether every bracket in the type is closed.
     */
    public static function balanced(string $type): bool
    {
        $depth = 0;
        $length = strlen($type);
        for ($i = 0; $i < $length; $i++) {
            $character = $type[$i];
            $i = self::quoteEnd($type, $i);
            $depth += self::depthChange($character);
            if ($depth < 0) {
                return false;
            }
        }

        return $depth === 0;
    }

    /**
     * The offset of the quote that closes a literal string type such as
     * `'a b'`, so a scan skips its text, or the offset itself for any other
     * character.
     */
    private static function quoteEnd(string $type, int $offset): int
    {
        $quote = $type[$offset];
        if ($quote !== "'" && $quote !== '"') {
            return $offset;
        }

        $length = strlen($type);
        for ($i = $offset + 1; $i < $length; $i++) {
            if ($type[$i] === '\\') {
                $i++;
                continue;
            }

            if ($type[$i] === $quote) {
                return $i;
            }
        }

        return $length - 1;
    }

    /**
     * How a character moves the bracket depth: 1 for an opening bracket,
     * -1 for a closing one, 0 for anything else.
     */
    private static function depthChange(string $character): int
    {
        if (str_contains(self::OPENING, $character)) {
            return 1;
        }

        return str_contains(self::CLOSING, $character) ? -1 : 0;
    }
}
