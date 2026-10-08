<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use PhpToken;

use function array_values;
use function count;
use function ltrim;
use function str_starts_with;
use function strpbrk;
use function strtolower;
use function strtr;
use function substr;

use const T_ARRAY;
use const T_CONSTANT_ENCAPSED_STRING;
use const T_FUNCTION;
use const T_NAME_FULLY_QUALIFIED;
use const T_STRING;
use const T_VARIABLE;

/**
 * The method names a `trustedCallbacks()` body returns, read off its tokens.
 *
 * Two body shapes are understood: `return <list>;`, and a variable set to a
 * list, grown with `$variable[] = 'name';` and returned. A list is an array
 * of string literals, `parent::trustedCallbacks()` or an `array_merge()` of
 * lists. Any other body gives no answer, since its result cannot be known
 * without running it.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class TrustedCallbackList
{
    /**
     * @var list<PhpToken>
     */
    private array $tokens = [];

    private int $at = 0;

    /**
     * @param list<string> $names The literal names, case kept, since core
     *   compares them strictly.
     * @param bool $parent Whether the parent's list is part of the result.
     */
    public function __construct(
        public readonly array $names,
        public readonly bool $parent,
    ) {}

    /**
     * Reads the method declared in $source, from its signature or anything
     * before it to its closing brace.
     */
    public static function parse(string $source): ?self
    {
        $reader = new self([], false);
        foreach (PhpToken::tokenize('<?php ' . $source) as $token) {
            if ($token->isIgnorable()) {
                continue;
            }

            $reader->tokens[] = $token;
        }

        return $reader->body();
    }

    private function body(): ?self
    {
        while ($this->at < count($this->tokens) && !$this->tokens[$this->at]->is(T_FUNCTION)) {
            $this->at++;
        }

        while ($this->at < count($this->tokens) && $this->tokens[$this->at]->text !== '{') {
            $this->at++;
        }

        if (!$this->take('{')) {
            return null;
        }

        $list = $this->take('return') ? $this->expression(null, null) : $this->grown();

        return $list !== null && $this->take(';') && $this->take('}') ? $list : null;
    }

    /**
     * `$list = <list>; $list[] = 'name'; return <list using $list>`, up to
     * the semicolon after the returned list.
     */
    private function grown(): ?self
    {
        $variable = $this->peek();
        if ($variable?->is(T_VARIABLE) !== true) {
            return null;
        }

        $this->at++;
        $list = $this->take('=') ? $this->expression(null, null) : null;
        if ($list === null || !$this->take(';')) {
            return null;
        }

        $names = $list->names;
        while ($this->peek()?->text === $variable->text) {
            $this->at++;
            $name = $this->take('[') && $this->take(']') && $this->take('=') ? $this->string() : null;
            if ($name === null || !$this->take(';')) {
                return null;
            }

            $names[] = $name;
        }

        return $this->take('return') ? $this->expression($variable->text, new self($names, $list->parent)) : null;
    }

    /**
     * A list: an array of string literals, the parent's list, or an
     * `array_merge()` of lists. $variable and $value stand for a variable the
     * body set before.
     */
    private function expression(?string $variable, ?self $value): ?self
    {
        $token = $this->peek();
        if ($token === null) {
            return null;
        }

        if ($token->text === '[' || $token->is(T_ARRAY)) {
            return $this->literalArray();
        }

        if ($variable !== null && $token->text === $variable) {
            $this->at++;

            return $value;
        }

        $name = strtolower(ltrim($token->text, characters: '\\'));
        if ($token->is(T_STRING) && $name === 'parent') {
            $this->at++;

            return (
                $this->take('::') && $this->take('trustedcallbacks') && $this->take('(') && $this->take(')')
                    ? new self([], true)
                    : null
            );
        }

        if (!$token->is([T_STRING, T_NAME_FULLY_QUALIFIED]) || $name !== 'array_merge') {
            return null;
        }

        $this->at++;
        if (!$this->take('(')) {
            return null;
        }

        $names = [];
        $parent = false;
        do {
            if ($this->peek()?->text === ')') {
                break;
            }

            $part = $this->expression($variable, $value);
            if ($part === null) {
                return null;
            }

            $names = [...$names, ...$part->names];
            if ($part->parent) {
                $parent = true;
            }
        } while ($this->take(','));

        return $this->take(')') ? new self($names, $parent) : null;
    }

    /**
     * `['a', 'b']` or `array('a', 'b')`, strings only.
     */
    private function literalArray(): ?self
    {
        $close = $this->peek()?->is(T_ARRAY) === true ? ')' : ']';
        $this->at++;
        if ($close === ')' && !$this->take('(')) {
            return null;
        }

        $names = [];
        while (!$this->take($close)) {
            $name = $this->string();
            if ($name === null) {
                return null;
            }

            $names[] = $name;
            if (!$this->take(',') && $this->peek()?->text !== $close) {
                return null;
            }
        }

        return new self(array_values($names), false);
    }

    /**
     * The value of a string literal without escapes or variables.
     */
    private function string(): ?string
    {
        $token = $this->peek();
        if ($token?->is(T_CONSTANT_ENCAPSED_STRING) !== true) {
            return null;
        }

        $this->at++;
        $inner = substr($token->text, offset: 1, length: -1);
        if (str_starts_with($token->text, "'")) {
            return strtr($inner, ['\\\\' => '\\', "\\'" => "'"]);
        }

        return strpbrk($inner, characters: '\\$') === false ? $inner : null;
    }

    private function peek(): ?PhpToken
    {
        return $this->tokens[$this->at] ?? null;
    }

    /**
     * Moves past the next token when its text is $text, case-insensitively.
     */
    private function take(string $text): bool
    {
        if (strtolower($this->peek()->text ?? '') !== $text) {
            return false;
        }

        $this->at++;

        return true;
    }
}
