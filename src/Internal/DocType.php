<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function str_contains;
use function strlen;
use function substr;
use function trim;

/**
 * Reads phpDoc types, which may hold spaces inside brackets and quotes.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class DocType
{
    private const OPENING = '<{([';

    private const CLOSING = '>})]';

    private const WHITESPACE = " \t\n\r\v\f";

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
