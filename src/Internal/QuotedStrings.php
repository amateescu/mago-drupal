<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function chr;
use function ctype_digit;
use function hexdec;
use function ltrim;
use function mb_chr;
use function octdec;
use function preg_replace_callback;
use function str_starts_with;
use function strtr;
use function substr;

/**
 * Reads the value of a single-quoted or double-quoted string literal from
 * its source text.
 *
 * @internal
 */
final class QuotedStrings
{
    /**
     * The escape sequences that PHP decodes in a double-quoted string. A
     * backslash before any other character stays in the value.
     */
    private const ESCAPE = '/\\\\(?:[nrtvef\\\\$"]|[0-7]{1,3}|x[\da-fA-F]{1,2}|u\{[\da-fA-F]+\})/';

    private const CONTROL_ESCAPES = ['n' => "\n", 'r' => "\r", 't' => "\t", 'v' => "\v", 'e' => "\e", 'f' => "\f"];

    private function __construct() {}

    /**
     * Returns the value that PHP reads from the source text of a string
     * literal without variables.
     */
    public static function value(string $text): string
    {
        // A `b` prefix marks a binary string. It does not change the value.
        $text = ltrim($text, characters: 'bB');

        // Only the first and last characters are delimiters. A quote between
        // them is content, so `'"'` holds one double quote.
        $content = substr($text, offset: 1, length: -1);
        if (str_starts_with($text, "'")) {
            return strtr($content, ['\\\\' => '\\', "\\'" => "'"]);
        }

        return (string) preg_replace_callback(self::ESCAPE, self::escape(...), $content);
    }

    /**
     * Returns the bytes that one escape sequence of a double-quoted string
     * stands for.
     *
     * @param array<array-key, string> $match
     */
    private static function escape(array $match): string
    {
        $sequence = $match[0];
        $letter = $sequence[1];

        return match (true) {
            $letter === 'x' => chr((int) hexdec(substr($sequence, offset: 2))),
            $letter === 'u' => (string) mb_chr(
                (int) hexdec(substr($sequence, offset: 3, length: -1)),
                encoding: 'UTF-8',
            ),
            ctype_digit($letter) => chr((int) octdec(substr($sequence, offset: 1))),
            default => self::CONTROL_ESCAPES[$letter] ?? $letter,
        };
    }
}
