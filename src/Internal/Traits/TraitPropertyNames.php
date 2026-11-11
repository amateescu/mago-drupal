<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Traits;

use amateescu\MagoDrupal\Internal\Text\PhpTokens;
use PhpToken;

use function array_diff_key;
use function array_keys;
use function count;
use function file_get_contents;
use function is_array;
use function is_file;
use function is_string;
use function ksort;
use function str_contains;
use function stripos;
use function substr;

use const T_CURLY_OPEN;
use const T_DOLLAR_OPEN_CURLY_BRACES;
use const T_DOUBLE_COLON;
use const T_NAME_QUALIFIED;
use const T_NAMESPACE;
use const T_NEW;
use const T_NULLSAFE_OBJECT_OPERATOR;
use const T_OBJECT_OPERATOR;
use const T_STRING;
use const T_TRAIT;
use const T_VARIABLE;

/**
 * The names of the properties that each trait under a Drupal root reads on
 * `$this` without declaring them, read off the PHP files.
 *
 * The property provider needs the traits and the names before analysis
 * starts, as its targets, and metadata isn't available then. A name too many
 * costs the provider a request that it answers with nothing, so a property
 * that the trait gets from another trait counts as not declared.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class TraitPropertyNames
{
    /**
     * @param array<non-empty-string, non-empty-list<non-empty-string>> $names
     *   The names, without the leading `$`, by the name of the trait.
     */
    private function __construct(
        public readonly array $names,
    ) {}

    /**
     * The names that the traits of one file read and don't declare, by the
     * name of the trait, in the shape merge() takes, or null for a file
     * without one.
     *
     * @return array<non-empty-string, list<non-empty-string>>|null
     */
    public static function file(string $file): ?array
    {
        $source = is_file($file) ? file_get_contents($file) : false;
        if ($source === false || stripos($source, needle: 'trait') === false || !str_contains($source, '$this->')) {
            return null;
        }

        $tokens = PhpToken::tokenize($source);
        $traits = [];
        $namespace = '';
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            if ($tokens[$i]->is(T_NAMESPACE)) {
                $next = PhpTokens::next($tokens, $i + 1);
                $namespace = $next !== null && $tokens[$next]->is([T_STRING, T_NAME_QUALIFIED])
                    ? $tokens[$next]->text . '\\'
                    : '';
                continue;
            }

            $declaration = $tokens[$i]->is(T_TRAIT) ? self::declaration($tokens, $i) : null;
            if ($declaration === null) {
                continue;
            }

            [$name, $open] = $declaration;
            $names = [];
            $i = self::body($tokens, $open, $names);
            if ($names !== []) {
                $traits[$namespace . $name] = array_keys($names);
            }
        }

        return $traits === [] ? null : $traits;
    }

    /**
     * @param array<string, mixed> $files What file() returned for each file.
     */
    public static function merge(array $files): self
    {
        $traits = [];
        /** @var mixed $found */
        foreach ($files as $found) {
            /** @var mixed $names */
            foreach (is_array($found) ? $found : [] as $trait => $names) {
                if (!is_string($trait) || $trait === '' || !is_array($names)) {
                    continue;
                }

                /** @var mixed $name */
                foreach ($names as $name) {
                    if (!is_string($name) || $name === '') {
                        continue;
                    }

                    $traits[$trait][$name] = true;
                }
            }
        }

        ksort($traits);
        $merged = [];
        foreach ($traits as $trait => $names) {
            $merged[$trait] = array_keys($names);
        }

        return new self($merged);
    }

    /**
     * The name of the trait declared at the index and the index of the brace
     * that opens its body, or null for `Foo::trait` and other uses of the
     * word.
     *
     * @param list<PhpToken> $tokens
     *
     * @return array{non-empty-string, int}|null
     */
    private static function declaration(array $tokens, int $index): ?array
    {
        $previous = PhpTokens::previous($tokens, $index - 1);
        $name = PhpTokens::next($tokens, $index + 1);
        $open = $name === null ? null : PhpTokens::next($tokens, $name + 1);
        if (
            $previous !== null && $tokens[$previous]->is([T_DOUBLE_COLON, T_NEW])
            || $name === null
            || $open === null
            || !$tokens[$name]->is(T_STRING)
            || $tokens[$open]->text !== '{'
            || $tokens[$name]->text === ''
        ) {
            return null;
        }

        return [$tokens[$name]->text, $open];
    }

    /**
     * Adds the names that a trait body reads on `$this` and doesn't declare,
     * and returns the index of the brace that closes the body.
     *
     * @param list<PhpToken> $tokens
     * @param array<non-empty-string, true> $names
     */
    private static function body(array $tokens, int $open, array &$names): int
    {
        $read = [];
        $declared = [];
        $braces = 0;
        $parentheses = 0;
        $count = count($tokens);
        for ($i = $open; $i < $count; $i++) {
            $token = $tokens[$i];
            $text = $token->text;
            if ($text === '{' || $token->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
                $braces++;
                continue;
            }

            if ($text === '}') {
                $braces--;
                if ($braces === 0) {
                    break;
                }

                continue;
            }

            if ($text === '(' || $text === ')') {
                $parentheses += $text === '(' ? 1 : -1;
                continue;
            }

            if (!$token->is(T_VARIABLE)) {
                continue;
            }

            // A variable among the members, outside a parameter list, is a
            // property that the trait declares.
            if ($braces === 1 && $parentheses === 0) {
                $declared[substr($text, offset: 1)] = true;
                continue;
            }

            $name = $text === '$this' ? self::propertyRead($tokens, $i) : null;
            if ($name !== null) {
                $read[$name] = true;
            }
        }

        foreach (array_keys(array_diff_key($read, $declared)) as $name) {
            $names[$name] = true;
        }

        return $i;
    }

    /**
     * The name of the property read on the `$this` at the index, or null for
     * a method call or anything else.
     *
     * @param list<PhpToken> $tokens
     *
     * @return non-empty-string|null
     */
    private static function propertyRead(array $tokens, int $index): ?string
    {
        $arrow = PhpTokens::next($tokens, $index + 1);
        $name = $arrow === null ? null : PhpTokens::next($tokens, $arrow + 1);
        $after = $name === null ? null : PhpTokens::next($tokens, $name + 1);
        if (
            $arrow === null
            || $name === null
            || !$tokens[$arrow]->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR])
            || !$tokens[$name]->is(T_STRING)
            || $after !== null && $tokens[$after]->text === '('
            || $tokens[$name]->text === ''
        ) {
            return null;
        }

        return $tokens[$name]->text;
    }
}
