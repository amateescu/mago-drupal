<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use ParseError;
use PhpToken;

use function count;

/**
 * The named functions and methods of one file, with their byte ranges,
 * docblocks and the class-likes they are declared in.
 *
 * Closures and arrow functions are left out, so the function around an
 * offset inside a closure is the one the closure sits in.
 *
 * @internal
 */
final class NamedFunctions
{
    /**
     * Tokens that may sit between a docblock and its function.
     */
    private const MODIFIERS = [
        T_ABSTRACT,
        T_FINAL,
        T_PUBLIC,
        T_PROTECTED,
        T_PRIVATE,
        T_STATIC,
        T_READONLY,
    ];

    /**
     * @param list<array{int, int, string, string, ?string}> $functions Start
     *   and end offsets, name, docblock and class-like of each function, in
     *   source order.
     */
    private function __construct(
        private readonly array $functions,
    ) {}

    public static function of(string $contents): self
    {
        try {
            $tokens = PhpToken::tokenize($contents);

            // The host analyzes files Mago's own parser accepts, which is not
            // always what PHP's tokenizer accepts. A file it rejects has no
            // functions rather than taking the worker down.
            // @mago-expect analysis:avoid-catching-error
        } catch (ParseError) {
            return new self([]);
        }

        return new self(self::scan($tokens, ClassLikeRanges::scan($tokens)));
    }

    /**
     * The name, docblock and class-like of the innermost named function
     * around the offset, or null when the offset is outside every function.
     * The class-like is null for a function outside a named one.
     *
     * A function nested in another comes after it in source order, so the
     * last match is the innermost.
     *
     * @return array{string, string, ?string}|null
     */
    public function at(int $offset): ?array
    {
        $found = null;
        foreach ($this->functions as [$start, $end, $name, $docblock, $classLike]) {
            if ($offset < $start || $offset >= $end) {
                continue;
            }

            $found = [$name, $docblock, $classLike];
        }

        return $found;
    }

    /**
     * @param list<PhpToken> $tokens
     *
     * @return list<array{int, int, string, string, ?string}>
     */
    private static function scan(array $tokens, ClassLikeRanges $classLikes): array
    {
        $functions = [];
        $docblock = '';
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token->is(T_DOC_COMMENT)) {
                $docblock = $token->text;
                continue;
            }

            if ($token->is(T_ATTRIBUTE)) {
                $i = PhpTokens::closer($tokens, $i, '[', ']');
                continue;
            }

            if ($token->isIgnorable() || $token->is(self::MODIFIERS)) {
                continue;
            }

            $name = $token->is(T_FUNCTION) ? self::name($tokens, $i) : null;
            if ($name !== null) {
                $functions[] = [
                    $token->pos,
                    PhpTokens::bodyEnd($tokens, $i),
                    $name,
                    $docblock,
                    $classLikes->at($token->pos),
                ];
            }

            $docblock = '';
        }

        return $functions;
    }

    /**
     * The name after the `function` keyword at `$index`, or null for a
     * closure.
     *
     * @param list<PhpToken> $tokens
     */
    private static function name(array $tokens, int $index): ?string
    {
        $name = PhpTokens::next($tokens, $index + 1);
        if ($name !== null && $tokens[$name]->text === '&') {
            $name = PhpTokens::next($tokens, $name + 1);
        }

        return $name !== null && $tokens[$name]->is(T_STRING) ? $tokens[$name]->text : null;
    }
}
