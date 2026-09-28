<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use PhpToken;

use function count;
use function ltrim;

use const T_ABSTRACT;
use const T_ATTRIBUTE;
use const T_CLASS;
use const T_COMMENT;
use const T_DOC_COMMENT;
use const T_DOUBLE_COLON;
use const T_FINAL;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;
use const T_NAMESPACE;
use const T_NEW;
use const T_READONLY;
use const T_STRING;
use const T_WHITESPACE;

/**
 * Reads the classes of a PHP file off its tokens, with the docblock, the
 * attributes and the `abstract` modifier each is declared with.
 *
 * `AnnotatedDeclarations` reads every file under the Drupal root that can
 * declare an annotated class with it, analyzed or not.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class AnnotatedClasses
{
    /**
     * Tokens between a docblock and the class keyword that keep the docblock
     * attached.
     */
    private const BETWEEN = [T_WHITESPACE, T_COMMENT, T_FINAL, T_READONLY];

    private string $namespace = '';

    private ?string $docblock = null;

    /**
     * @var array<string, true>
     */
    private array $attributes = [];

    private bool $abstract = false;

    private ?PhpToken $previous = null;

    /**
     * @var list<array{non-empty-string, string, array<string, true>, bool}>
     */
    private array $classes = [];

    private function __construct() {}

    /**
     * Every class declared with a docblock: its name, the docblock, the short
     * names of its attributes as keys, and whether it is abstract.
     *
     * @return list<array{non-empty-string, string, array<string, true>, bool}>
     */
    public static function inContents(string $contents): array
    {
        $walk = new self();
        $tokens = PhpToken::tokenize($contents);
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $i = $walk->token($tokens, $i);
        }

        return $walk->classes;
    }

    /**
     * Handles the token at the index and returns the index of the last one
     * it used.
     *
     * @param list<PhpToken> $tokens
     */
    private function token(array $tokens, int $index): int
    {
        $token = $tokens[$index];
        if ($token->is(T_DOC_COMMENT)) {
            [$this->docblock, $this->attributes, $this->abstract] = [$token->text, [], false];
            return $index;
        }

        if ($token->is(T_ATTRIBUTE)) {
            $this->attributes[self::attributeName($tokens, $index)] = true;
            return PhpTokens::closer($tokens, $index, '[', ']');
        }

        if ($token->is(T_ABSTRACT) || $token->is(self::BETWEEN)) {
            $this->abstract = $this->abstract || $token->is(T_ABSTRACT);
            return $index;
        }

        if ($token->is(T_NAMESPACE)) {
            $next = PhpTokens::next($tokens, $index + 1);
            $this->namespace = $next !== null && $tokens[$next]->is([T_STRING, T_NAME_QUALIFIED])
                ? $tokens[$next]->text . '\\'
                : '';
        }

        if ($token->is(T_CLASS)) {
            $this->declaration($tokens, $index);
        }

        [$this->docblock, $this->attributes, $this->abstract, $this->previous] = [null, [], false, $token];

        return $index;
    }

    /**
     * Records a class declared with a docblock, not `Foo::class` or `new
     * class`.
     *
     * @param list<PhpToken> $tokens
     */
    private function declaration(array $tokens, int $index): void
    {
        if ($this->docblock === null || $this->previous?->is([T_DOUBLE_COLON, T_NEW]) === true) {
            return;
        }

        $next = PhpTokens::next($tokens, $index + 1);
        $name = $next !== null && $tokens[$next]->is(T_STRING) ? $this->namespace . $tokens[$next]->text : '';
        if ($name !== '') {
            $this->classes[] = [$name, $this->docblock, $this->attributes, $this->abstract];
        }
    }

    /**
     * The short name of the attribute class the `#[` at the index opens.
     *
     * @param list<PhpToken> $tokens
     */
    private static function attributeName(array $tokens, int $index): string
    {
        $next = PhpTokens::next($tokens, $index + 1);
        if ($next === null || !$tokens[$next]->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
            return '';
        }

        return ClassNames::short(ltrim($tokens[$next]->text, characters: '\\'));
    }
}
