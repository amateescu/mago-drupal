<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use PhpToken;

use function count;
use function file_get_contents;
use function is_file;
use function str_contains;
use function strtolower;

use const T_ABSTRACT;
use const T_ATTRIBUTE;
use const T_CLASS;
use const T_COMMENT;
use const T_CONST;
use const T_CURLY_OPEN;
use const T_DOC_COMMENT;
use const T_DOLLAR_OPEN_CURLY_BRACES;
use const T_DOUBLE_COLON;
use const T_ENUM;
use const T_FINAL;
use const T_FUNCTION;
use const T_INTERFACE;
use const T_NAME_QUALIFIED;
use const T_NAMESPACE;
use const T_NEW;
use const T_PRIVATE;
use const T_PROTECTED;
use const T_PUBLIC;
use const T_READONLY;
use const T_STATIC;
use const T_STRING;
use const T_TRAIT;
use const T_VAR;
use const T_WHITESPACE;

/**
 * Reads the `@deprecated` class-likes, class constants, properties and
 * methods off PHP files.
 *
 * One walk over a file's tokens keeps the namespace, the class-like whose
 * body is open and the last docblock. A docblock counts for the declaration
 * right after it, with only whitespace, comments, attributes and modifiers in
 * between.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class DeprecatedSymbolScan
{
    private const TAG = '@deprecated';

    /**
     * Tokens that may sit between a docblock and what it documents.
     */
    private const MODIFIERS = [
        T_WHITESPACE,
        T_COMMENT,
        T_PUBLIC,
        T_PROTECTED,
        T_PRIVATE,
        T_STATIC,
        T_READONLY,
        T_FINAL,
        T_ABSTRACT,
        T_VAR,
    ];

    private const DECLARATIONS = [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM];

    /**
     * The symbols found so far, in the shape `DeprecatedSymbols` takes them.
     *
     * @var array{
     *   classLikes: array<string, string>,
     *   interfaces: list<non-empty-string>,
     *   constants: array<string, string>,
     *   properties: array<string, string>,
     *   methods: array<string, string>,
     * }
     */
    private array $found = [
        'classLikes' => [],
        'interfaces' => [],
        'constants' => [],
        'properties' => [],
        'methods' => [],
    ];

    private string $namespace = '';

    /**
     * The class-like whose body is open, as written, or null outside one.
     */
    private ?string $class = null;

    /**
     * A class-like declared but whose body has not opened yet.
     */
    private ?string $pending = null;

    private int $classDepth = 0;

    private int $depth = 0;

    /**
     * The `@deprecated` text of the last docblock, until something other
     * than a modifier follows it.
     */
    private ?string $text = null;

    private function __construct() {}

    /**
     * @param list<string> $files
     */
    public static function files(array $files): DeprecatedSymbols
    {
        $scan = new self();
        foreach ($files as $file) {
            $source = is_file($file) ? file_get_contents($file) : false;
            if ($source === false || !str_contains($source, self::TAG)) {
                continue;
            }

            [$scan->namespace, $scan->class, $scan->pending, $scan->depth, $scan->text] = ['', null, null, 0, null];
            $scan->walk(PhpToken::tokenize($source));
        }

        $found = $scan->found;

        return new DeprecatedSymbols(
            $found['classLikes'],
            $found['interfaces'],
            $found['constants'],
            $found['properties'],
            $found['methods'],
        );
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private function walk(array $tokens): void
    {
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token->is(T_DOC_COMMENT)) {
                $this->text = DeprecatedTag::text($token->text);
                continue;
            }

            if ($token->is(T_ATTRIBUTE)) {
                $i = PhpTokens::closer($tokens, $i, '[', ']');
                continue;
            }

            if (!$token->is(self::MODIFIERS)) {
                $this->token($tokens, $i);
            }
        }
    }

    /**
     * Handles a token that ends any docblock before it.
     *
     * @param list<PhpToken> $tokens
     */
    private function token(array $tokens, int $index): void
    {
        $token = $tokens[$index];
        $text = $this->text;
        $this->text = null;
        if ($token->is(T_NAMESPACE)) {
            $this->namespace = self::namespaceName($tokens, $index);
            return;
        }

        if ($token->is(self::DECLARATIONS) && self::declaresClassLike($tokens, $index)) {
            $this->declaration($tokens, $index, $text);
            return;
        }

        if ($token->text === '{' || $token->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
            $this->open();
            return;
        }

        if ($token->text === '}') {
            $this->close();
            return;
        }

        if ($text !== null && $this->class !== null && $this->depth === $this->classDepth) {
            $this->member($tokens, $index, $this->class, $text);
        }
    }

    /**
     * Records a class-like declaration, whose body opens at the next brace.
     *
     * @param list<PhpToken> $tokens
     */
    private function declaration(array $tokens, int $index, ?string $text): void
    {
        $next = PhpTokens::next($tokens, $index + 1);
        $name = $next === null ? '' : $tokens[$next]->text;
        $declared = ($this->namespace === '' ? '' : $this->namespace . '\\') . $name;
        $this->pending = $declared;
        if ($text === null || $declared === '' || $name === '') {
            return;
        }

        $this->found['classLikes'][strtolower($declared)] = $text;
        if ($tokens[$index]->is(T_INTERFACE)) {
            $this->found['interfaces'][] = $declared;
        }
    }

    /**
     * Opens a brace, which is a class-like's body after its declaration.
     */
    private function open(): void
    {
        $this->depth++;
        if ($this->pending !== null) {
            [$this->class, $this->classDepth, $this->pending] = [$this->pending, $this->depth, null];
        }
    }

    /**
     * Closes a brace, and the class-like's body with its own.
     */
    private function close(): void
    {
        if ($this->class !== null && $this->depth === $this->classDepth) {
            $this->class = null;
        }

        $this->depth--;
    }

    /**
     * Records the constants or the property a deprecated member declaration
     * starting at the index names.
     *
     * @param list<PhpToken> $tokens
     */
    private function member(array $tokens, int $index, string $class, string $text): void
    {
        $token = $tokens[$index];
        if ($token->is(T_FUNCTION)) {
            $method = DeclarationTokens::functionName($tokens, $index);
            if ($method !== null) {
                $this->found['methods'][$class . '::' . $method] = $text;
            }

            return;
        }

        $lowercased = strtolower($class);
        if ($token->is(T_CONST)) {
            foreach (DeclarationTokens::constantNames($tokens, $index) as $constant) {
                $this->found['constants'][$lowercased . '::' . $constant] = $text;
            }

            return;
        }

        $property = DeclarationTokens::propertyName($tokens, $index);
        if ($property !== null) {
            $this->found['properties'][$lowercased . '::' . $property] = $text;
        }
    }

    /**
     * The namespace a `namespace` keyword at the index declares, or an empty
     * string for the global one.
     *
     * @param list<PhpToken> $tokens
     */
    private static function namespaceName(array $tokens, int $index): string
    {
        $next = PhpTokens::next($tokens, $index + 1);

        return $next !== null && $tokens[$next]->is([T_STRING, T_NAME_QUALIFIED]) ? $tokens[$next]->text : '';
    }

    /**
     * Whether the keyword at the index declares a class-like: not `Foo::class`
     * and not `new class`.
     *
     * @param list<PhpToken> $tokens
     */
    private static function declaresClassLike(array $tokens, int $index): bool
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            if (!$tokens[$i]->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
                return !$tokens[$i]->is([T_DOUBLE_COLON, T_NEW]);
            }
        }

        return true;
    }
}
