<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function ord;
use function preg_match;
use function preg_replace_callback;
use function sprintf;

/**
 * Makes text valid UTF-8 for Mago, which rejects issue text that is not.
 *
 * @internal
 */
final class Utf8
{
    /**
     * One valid UTF-8 sequence, or one byte that starts none in group 1.
     */
    private const SEQUENCE =
        '/[\x00-\x7F]+|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]'
            . '|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}'
            . '|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2}|(.)/s';

    public static function isValid(string $text): bool
    {
        return preg_match('//u', $text) === 1;
    }

    /**
     * Writes each byte that is not part of a valid UTF-8 sequence as `\xHH`,
     * the way Mago prints one.
     */
    public static function valid(string $text): string
    {
        if (self::isValid($text)) {
            return $text;
        }

        return (string) preg_replace_callback(
            self::SEQUENCE,
            static fn(array $match): string => ($match[1] ?? '') === '' ? $match[0] : sprintf('\x%02X', ord($match[1])),
            $text,
        );
    }

    public static function optional(?string $text): ?string
    {
        return $text === null ? null : self::valid($text);
    }
}
