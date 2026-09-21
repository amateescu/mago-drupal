<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function array_key_exists;
use function is_array;
use function is_int;
use function is_string;

/**
 * Narrows a `mixed` value to the shape that the caller expects.
 *
 * A nullable lookup, a parsed document or a loosely typed array returns
 * `mixed`. These helpers take that value as an argument and return a typed
 * one, so a caller never holds an untyped variable.
 *
 * @internal
 */
final class Shape
{
    private function __construct() {}

    public static function string(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    /**
     * @return non-empty-string|null
     */
    public static function nonEmptyString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function int(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public static function array(mixed $value): ?array
    {
        return is_array($value) ? $value : null;
    }

    /**
     * Returns the string at the path that the keys make through nested
     * arrays.
     */
    public static function stringAt(mixed $value, string $key, string ...$rest): ?string
    {
        if (!is_array($value) || !array_key_exists($key, $value)) {
            return null;
        }

        return $rest === [] ? self::string($value[$key]) : self::stringAt($value[$key], ...$rest);
    }

    /**
     * Returns the array at the path that the keys make, or an empty array.
     *
     * @return array<array-key, mixed>
     */
    public static function arrayAt(mixed $value, string $key, string ...$rest): array
    {
        if (!is_array($value) || !array_key_exists($key, $value)) {
            return [];
        }

        return $rest === [] ? self::array($value[$key]) ?? [] : self::arrayAt($value[$key], ...$rest);
    }
}
