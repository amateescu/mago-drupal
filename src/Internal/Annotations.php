<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function array_key_exists;
use function count;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;
use function preg_match;
use function preg_replace;
use function str_contains;
use function strlen;
use function strtolower;
use function substr;

/**
 * Parses Doctrine-style annotations out of a docblock.
 *
 * Drupal's legacy plugin and entity type declarations look like
 * `@ContentEntityType(id = "node", handlers = {"storage" = "…"})`. The parser
 * understands the shapes those declarations use. Those are `key = value`
 * pairs, quoted strings, numbers, booleans, `{ … }` maps and lists, and
 * nested annotations such as `@Translation("…")`, which are kept as their
 * first argument.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class Annotations
{
    /**
     * `@Name(`; the paren has to sit on the same line, or a `@todo` followed
     * by a parenthesised remark would count.
     */
    private const OPENING = '/@([A-Za-z_][A-Za-z0-9_\\\\]*)[ \t]*\(/';

    private const NUMBER = '/\G[+-]?(?:0[xX][0-9A-Fa-f]+|\d*\.?\d+(?:[eE][+-]?\d+)?)/';

    private string $text = '';

    private int $position = 0;

    private function __construct() {}

    /**
     * Every `@Name(...)` annotation in the docblock, in order. Code samples
     * between `@code` and `@endcode` are skipped, and an annotation whose
     * parenthesis never closes is skipped on its own.
     *
     * @return list<Annotation>
     */
    public static function parse(string $docblock): array
    {
        $parser = new self();
        $parser->text = self::strip($docblock);
        $annotations = [];
        $matches = [];
        while (preg_match(self::OPENING, $parser->text, $matches, PREG_OFFSET_CAPTURE, $parser->position) === 1) {
            $name = $matches[1][0];
            $parser->position = (int) $matches[0][1] + strlen($matches[0][0]);
            $after = $parser->position;
            $arguments = $parser->arguments();
            if (is_array($arguments)) {
                $annotations[] = new Annotation($name, $arguments);
                continue;
            }

            $parser->position = $after;
        }

        return $annotations;
    }

    /**
     * Removes the comment frame and the code samples so the grammar sees
     * plain text.
     */
    private static function strip(string $docblock): string
    {
        $stripped = preg_replace('/^\s*\/\*\*|\*\/\s*$/', replacement: '', subject: $docblock) ?? $docblock;
        $stripped = preg_replace('/^\s*\*\s?/m', replacement: '', subject: $stripped) ?? $stripped;

        return preg_replace('/^@code\b.*?^@endcode\b/ms', replacement: '', subject: $stripped) ?? $stripped;
    }

    /**
     * Parses `key = value, …)` after an opening parenthesis; a lone value is
     * kept at position 0.
     *
     * @return array<array-key, mixed>|null
     */
    private function arguments(): ?array
    {
        $arguments = [];
        $position = 0;
        while (true) {
            $this->skipSpace();
            $next = $this->peek();
            if ($next === ')') {
                $this->position++;

                return $arguments;
            }

            if ($next === '') {
                return null;
            }

            if ($next === ',') {
                $this->position++;
                continue;
            }

            $key = $this->identifier();
            if ($key !== null) {
                $this->skipSpace();
                if ($this->peek() === '=') {
                    $this->position++;
                    $arguments[$key] = $this->value();
                    continue;
                }

                // A bare word is a constant or a boolean used as a value.
                $arguments[$position++] = self::word($key);
                continue;
            }

            $arguments[$position++] = $this->value();
        }
    }

    /**
     * @return string|int|float|bool|array<array-key, mixed>|null
     */
    private function value(): string|int|float|bool|array|null
    {
        $this->skipSpace();
        $next = $this->peek();
        if ($next === '"' || $next === "'") {
            return $this->string($next);
        }

        if ($next === '{') {
            $this->position++;

            return $this->map();
        }

        if ($next === '@') {
            return $this->nested();
        }

        $matches = [];
        if (preg_match(self::NUMBER, $this->text, $matches, offset: $this->position) === 1) {
            $this->position += strlen($matches[0]);

            return self::number($matches[0]);
        }

        $word = $this->identifier();
        if ($word !== null) {
            return self::word($word);
        }

        // Unknown token: skip one character so parsing always moves on.
        $this->position++;

        return null;
    }

    /**
     * `{ "a" = "b", "c" }` becomes `['a' => 'b', 0 => 'c']`; Doctrine also
     * takes `:` between key and value.
     *
     * @return array<array-key, mixed>
     */
    private function map(): array
    {
        $map = [];
        $position = 0;
        while (true) {
            $this->skipSpace();
            $next = $this->peek();
            if ($next === '}' || $next === '') {
                $this->position += $next === '' ? 0 : 1;

                return $map;
            }

            if ($next === ',') {
                $this->position++;
                continue;
            }

            $value = $this->value();
            $this->skipSpace();
            if ($this->peek() === '=' || $this->peek() === ':') {
                $this->position++;
                $key = is_string($value) || is_int($value) ? $value : $position++;
                $map[$key] = $this->value();
                continue;
            }

            $map[$position++] = $value;
        }
    }

    /**
     * A nested annotation such as `@Translation("Content")` is reduced to its
     * first argument, which is the label.
     *
     * @return string|int|float|bool|array<array-key, mixed>|null
     */
    private function nested(): string|int|float|bool|array|null
    {
        $matches = [];
        if (
            preg_match('/\G@([A-Za-z_][A-Za-z0-9_\\\\]*)[ \t]*\(/', $this->text, $matches, offset: $this->position)
            !== 1
        ) {
            $this->position++;

            return null;
        }

        $this->position += strlen($matches[0]);
        $arguments = $this->arguments() ?? [];
        if (array_key_exists(0, $arguments)) {
            return self::scalarOrArray($arguments[0]);
        }

        return count($arguments) === 0 ? null : $arguments;
    }

    /**
     * @return string|int|float|bool|array<array-key, mixed>|null
     */
    private static function scalarOrArray(mixed $value): string|int|float|bool|array|null
    {
        if (is_string($value) || is_int($value) || is_float($value) || is_bool($value) || is_array($value)) {
            return $value;
        }

        return null;
    }

    private function string(string $quote): string
    {
        $this->position++;
        $value = '';
        while ($this->position < strlen($this->text)) {
            $character = $this->text[$this->position++];
            if ($character === $quote) {
                if ($this->peek() === $quote) {
                    // Doubled quotes escape themselves in Doctrine annotations.
                    $value .= $quote;
                    $this->position++;
                    continue;
                }

                return $value;
            }

            $value .= $character;
        }

        return $value;
    }

    /**
     * A word, constant or class reference, `\Fully\Qualified::NAME` included.
     */
    private function identifier(): ?string
    {
        $matches = [];
        if (preg_match('/\G\\\\?[A-Za-z_][A-Za-z0-9_\\\\:]*/', $this->text, $matches, offset: $this->position) !== 1) {
            return null;
        }

        $this->position += strlen($matches[0]);

        return $matches[0];
    }

    private static function number(string $literal): int|float
    {
        if (str_contains(strtolower($literal), 'x')) {
            return (int) hexdec(substr($literal, offset: 2));
        }

        if (str_contains($literal, '.') || str_contains(strtolower($literal), 'e')) {
            return is_numeric($literal) ? (float) $literal : 0.0;
        }

        return (int) $literal;
    }

    private static function word(string $word): string|bool|null
    {
        return match (strtolower($word)) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => $word,
        };
    }

    private function skipSpace(): void
    {
        $matches = [];
        if (preg_match('/\G\s+/', $this->text, $matches, offset: $this->position) === 1) {
            $this->position += strlen($matches[0]);
        }
    }

    private function peek(): string
    {
        return substr($this->text, $this->position, length: 1);
    }
}
