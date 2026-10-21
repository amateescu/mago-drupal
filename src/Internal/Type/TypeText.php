<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Type;

use function array_intersect;
use function array_map;
use function preg_match;
use function strcspn;
use function strlen;
use function strpbrk;
use function strrev;
use function strtr;
use function substr;
use function trim;

/**
 * Reads a type the way Mago prints it in an issue message, such as
 * `null|string` or `array<array-key, int>`.
 *
 * Text that doesn't parse gives the answer that keeps the issue.
 *
 * Mago prints a literal string as its raw source text in quotes, as in
 * `string('it's')`, and doesn't escape the quotes inside it. So the text
 * between the first and the last quote can't be split with any certainty.
 * The literal `')|string|('` prints as `string('')|string|('')`, which looks
 * like a union with a top-level `string`. Only the text before the first
 * quote and after the last one is read.
 *
 * @internal
 */
final class TypeText
{
    /**
     * The types that hold every string.
     */
    private const STRING_TYPES = ['string', 'array-key', 'scalar', 'nonnull', 'mixed'];

    private const QUOTES = '\'"';

    private function __construct() {}

    /**
     * Whether one of the types of the union, at its top level, holds every
     * string.
     */
    public static function holdsStrings(string $union): bool
    {
        return array_intersect(self::topLevelParts($union, separator: '|'), self::STRING_TYPES) !== [];
    }

    /**
     * Whether the type is an `array<K, V>` or a `non-empty-array<K, V>` whose
     * key type holds every string.
     *
     * A quote anywhere in the type gives false, because then the brackets
     * can't be matched. `array<string, string('a')>|array<int, string('b')>`
     * is a union of two arrays. It is also how one array prints when its
     * value is the literal `a')>|array<int, string('b`.
     */
    public static function keysHoldStrings(string $type): bool
    {
        $generic = [];
        if (
            strpbrk($type, self::QUOTES) !== false
            || preg_match('/^(?:non-empty-)?array<(.+)>$/', $type, $generic) !== 1
        ) {
            return false;
        }

        $parameters = self::topLevelParts($generic[1], separator: ',');

        return $parameters !== [] && self::holdsStrings($parameters[0]);
    }

    /**
     * The parts of a type split at the separator outside brackets, trimmed.
     *
     * In a type with quotes, only the parts that end before the first quote
     * and the parts that start after the last quote are returned. The list
     * is empty when the brackets don't balance.
     *
     * @return list<string>
     */
    private static function topLevelParts(string $type, string $separator): array
    {
        $length = strlen($type);
        $first = strcspn($type, self::QUOTES);
        if ($first === $length) {
            $split = self::split($type, $separator);

            return $split === null || $split[2] !== 0 ? [] : [...$split[0], $split[1]];
        }

        // The text after the last quote is split from the end of the type,
        // where the depth is known. It is turned around with its brackets
        // flipped, so that it splits the same way.
        $last = $length - strcspn(strrev($type), self::QUOTES);
        $before = self::split(substr($type, offset: 0, length: $first), $separator);
        $after = self::split(self::turnAround(substr($type, $last)), $separator);

        return (
            $before === null || $after === null ? [] : [...$before[0], ...array_map(self::turnAround(...), $after[0])]
        );
    }

    /**
     * Reverses text and flips its brackets, so `a<b>|c` becomes `c|<b>a`.
     */
    private static function turnAround(string $text): string
    {
        return strtr(strrev($text), from: '<>{}()[]', to: '><}{)(][');
    }

    /**
     * Splits text at the separator outside brackets.
     *
     * Returns the trimmed parts that a separator ends, the trimmed rest after
     * the last separator, and the depth at the end. Returns null when a
     * bracket closes that the text didn't open.
     *
     * @return array{list<string>, string, int}|null
     */
    private static function split(string $text, string $separator): ?array
    {
        $parts = [];
        $depth = 0;
        $start = 0;
        for ($i = 0, $length = strlen($text); $i < $length; $i++) {
            $character = $text[$i];
            $depth += match ($character) {
                '<', '{', '(', '[' => 1,
                '>', '}', ')', ']' => -1,
                default => 0,
            };
            if ($depth < 0) {
                return null;
            }

            if ($depth === 0 && $character === $separator) {
                $parts[] = trim(substr($text, $start, $i - $start));
                $start = $i + 1;
            }
        }

        return [$parts, trim(substr($text, $start)), $depth];
    }
}
