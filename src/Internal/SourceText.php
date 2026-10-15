<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function preg_match;
use function str_contains;
use function strcspn;
use function strlen;
use function strpos;
use function strrpos;
use function strspn;
use function substr;

/**
 * Reads the code between two nodes from the source text.
 *
 * A linter snapshot holds only the subtrees of the nodes that a rule
 * targets, so a rule that looks past the end of its node reads the text.
 * The offsets these methods take must be in code, not inside a string or a
 * comment.
 *
 * @internal
 */
final class SourceText
{
    /**
     * A single-quoted string, or a double-quoted one with no `$name`, `${`
     * or `{$` in it.
     */
    private const CONSTANT_STRING = '/\G(?:\'(?:[^\'\\\\]++|\\\\.)*+\'|"(?:[^"\\\\${]++|\\\\.|\$(?![a-zA-Z_\x80-\xff{])|\{(?!\$))*+")/s';

    private function __construct() {}

    /**
     * Returns the offset after the comment that starts at $offset, or null
     * when no comment starts there. A `//` or `#` comment ends before its
     * line break.
     */
    public static function commentEnd(string $contents, int $offset): ?int
    {
        $start = substr($contents, $offset, length: 2);
        if ($start === '/*') {
            $close = strpos($contents, needle: '*/', offset: $offset + 2);

            return $close === false ? strlen($contents) : $close + 2;
        }

        // `#[` opens an attribute, not a comment.
        if ($start === '//' || $start !== '#[' && ($contents[$offset] ?? '') === '#') {
            return $offset + strcspn($contents, characters: "\r\n", offset: $offset);
        }

        return null;
    }

    /**
     * Returns the offset past the spaces, tabs and comments that follow
     * $offset on its line. A block comment that spans lines is skipped as a
     * whole.
     */
    public static function skipLineBlank(string $contents, int $offset): int
    {
        while (true) {
            $offset += strspn($contents, characters: " \t", offset: $offset);
            $end = self::commentEnd($contents, $offset);
            if ($end === null) {
                return $offset;
            }

            $offset = $end;
        }
    }

    /**
     * Returns the offset of the first code character at or after $offset.
     * Whitespace, line breaks and comments are skipped.
     */
    public static function skipBlank(string $contents, int $offset): int
    {
        $length = strlen($contents);
        while ($offset < $length) {
            $offset += strspn($contents, characters: " \t\r\n", offset: $offset);
            $end = self::commentEnd($contents, $offset);
            if ($end === null) {
                return $offset;
            }

            $offset = $end;
        }

        return $offset;
    }

    /**
     * Returns the offset of the last character before $offset that isn't a
     * space, a tab or a line break, or -1. The walk back copies nothing.
     */
    public static function lastNonBlank(string $contents, int $offset): int
    {
        $offset--;
        while ($offset >= 0 && str_contains(" \t\r\n", $contents[$offset])) {
            $offset--;
        }

        return $offset;
    }

    /**
     * Returns the offset where the line that holds $offset starts. A line
     * break belongs to the line it ends. The search with a negative offset
     * runs back from the character before $offset and copies nothing.
     */
    public static function lineStart(string $contents, int $offset): int
    {
        if ($offset <= 0) {
            return 0;
        }

        $start = strrpos($contents, needle: "\n", offset: $offset - 1 - strlen($contents));

        return $start === false ? 0 : $start + 1;
    }

    /**
     * Returns the offset after the quoted string that starts at $start, or
     * null when no string starts there or the string holds a variable. PHP
     * reads such a string as one constant token.
     */
    public static function constantStringEnd(string $contents, int $start): ?int
    {
        $matches = [];

        return preg_match(self::CONSTANT_STRING, $contents, $matches, offset: $start) === 1
            ? $start + strlen($matches[0])
            : null;
    }
}
