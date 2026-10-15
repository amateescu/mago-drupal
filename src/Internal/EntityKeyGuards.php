<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use PhpToken;

use function explode;
use function implode;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function strlen;
use function strtolower;
use function substr;
use function trim;

use const T_FN;
use const T_FUNCTION;
use const T_VARIABLE;

/**
 * Finds the check that guards a `getKey()` or `getLinkTemplate()` call on an
 * entity type: `hasKey()` or `hasLinkTemplate()` with the same key.
 *
 * Both getters return FALSE for a key that the entity type doesn't have, and
 * Mago doesn't narrow one call's result by another call on the same object.
 * This reads the code around the call instead. The call counts as guarded
 * when the matching check on the same receiver, with the same key, guards it
 * in the same function, in one of the shapes that IfGuards accepts.
 *
 * The receiver is a variable or a chain of properties, and the key a string
 * literal or a variable. An assignment to the receiver or to the key
 * variable between the two calls cancels the guard.
 *
 * @internal
 */
final class EntityKeyGuards
{
    /**
     * A getter call on a variable or a property chain, with a literal or a
     * variable key. A double-quoted key counts without variables or escapes.
     */
    private const GETTER = '/^(?<receiver>\$\w+(?:\s*->\s*\w+)*)\s*->\s*(?<getter>getKey|getLinkTemplate)\s*\(\s*(?<key>\'[^\'\\\\]*\'|"[^"\\\\$]*"|\$\w+)\s*\)$/i';

    /**
     * The check for each getter, keyed by the lowercased getter name.
     */
    private const CHECKS = [
        'getkey' => 'hasKey',
        'getlinktemplate' => 'hasLinkTemplate',
    ];

    private function __construct() {}

    /**
     * Whether the getter call between the byte offsets runs only when its
     * check is true for the same receiver and key.
     */
    public static function guarded(string $contents, int $start, int $end): bool
    {
        $call = [];
        if (preg_match(self::GETTER, substr($contents, $start, $end - $start), $call) !== 1) {
            return false;
        }

        $tokens = PhpTokens::of($contents);
        $at = PhpTokens::at($tokens, $start);
        if ($at === null) {
            return false;
        }

        $receiver = self::receiverPattern($call['receiver']);
        $key = self::keyPattern($call['key']);
        $name = self::CHECKS[strtolower($call['getter'])];
        $check = '/\G' . $receiver . '\s*->\s*(?i:' . $name . ')\s*\(\s*' . $key . '\s*\)/';
        $variable = trim(explode('->', $call['receiver'])[0]);
        for ($i = self::functionStart($tokens, $at, $end); $i < $at; $i++) {
            $match = [];
            if (
                !$tokens[$i]->is(T_VARIABLE)
                || $tokens[$i]->text !== $variable
                || preg_match($check, $contents, $match, offset: $tokens[$i]->pos) !== 1
            ) {
                continue;
            }

            $checkEnd = $tokens[$i]->pos + strlen($match[0]);
            $between = substr($contents, $checkEnd, $start - $checkEnd);
            if (
                $checkEnd <= $start
                && IfGuards::guards($contents, $tokens, $i, $checkEnd, $at)
                && !self::rebinds($between, "(?:{$receiver}|{$key})")
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * The index of the token that starts the innermost function or arrow
     * function around the span, or 0 outside any. A check outside it doesn't
     * count, since the function may run later.
     *
     * @param list<PhpToken> $tokens
     */
    private static function functionStart(array $tokens, int $at, int $end): int
    {
        for ($i = $at - 1; $i >= 0; $i--) {
            if ($tokens[$i]->is([T_FUNCTION, T_FN]) && PhpTokens::bodyEnd($tokens, $i) >= $end) {
                return $i;
            }
        }

        return 0;
    }

    /**
     * Whether the code assigns to what the pattern matches, or binds it in a
     * foreach.
     */
    private static function rebinds(string $code, string $pattern): bool
    {
        return (
            preg_match('/' . $pattern . '\s*(?:[-+*\/.%&|^]|\?\?|<<|>>|\*\*)?=(?![=>])/', $code) === 1
            || preg_match('/\bas\s+(?:[^;{)]*=>\s*)?&?\s*' . $pattern . '/i', $code) === 1
        );
    }

    /**
     * A pattern for the receiver, with any spacing around the arrows.
     */
    private static function receiverPattern(string $receiver): string
    {
        $parts = [];
        foreach (explode('->', (string) preg_replace('/\s+/', replacement: '', subject: $receiver)) as $part) {
            $parts[] = preg_quote($part, delimiter: '/');
        }

        return implode('\s*->\s*', $parts) . '\b';
    }

    /**
     * A pattern for the key: a variable, or the literal in either quotes.
     */
    private static function keyPattern(string $key): string
    {
        if ($key[0] === '$') {
            return preg_quote($key, delimiter: '/') . '\b';
        }

        $value = preg_quote(substr($key, offset: 1, length: -1), delimiter: '/');

        return "(?:'{$value}'|\"{$value}\")";
    }
}
