<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Span;
use PhpToken;

use function count;
use function str_contains;
use function str_ends_with;

/**
 * The byte ranges of one file where a deprecated call is expected.
 *
 * Four things mark a scope: a `@group legacy` docblock in a test file, a
 * `@deprecated` docblock, a PHPUnit `#[IgnoreDeprecations]` attribute and a
 * call to `DeprecationHelper::backwardsCompatibleCall()`. The first three
 * apply to the class or the function they sit on, the last to the call's
 * arguments. Deprecated code may use other deprecated code. The legacy group
 * is a test runner convention, so it marks nothing outside the tests.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class DeprecationScopes
{
    private const LEGACY_GROUP = '@group legacy';

    private const DEPRECATED = '@deprecated';

    private const IGNORE_ATTRIBUTE = 'IgnoreDeprecations';

    private const HELPER_CALL = 'backwardsCompatibleCall';

    private const HELPER_CLASS = 'DeprecationHelper';

    /**
     * Tokens that may sit between an annotation block and its declaration.
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

    private const DECLARATIONS = [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM, T_FUNCTION];

    /**
     * @param list<array{int, int}> $ranges Half-open byte ranges.
     */
    private function __construct(
        private readonly array $ranges,
    ) {}

    /**
     * Whether the file can hold a deprecation scope at all.
     *
     * A plain substring screen, so the tokenizer only runs on files that
     * mark one.
     */
    public static function marked(string $contents, string $path): bool
    {
        return (
            str_contains($contents, self::DEPRECATED)
            || str_contains($contents, self::IGNORE_ATTRIBUTE)
            || str_contains($contents, self::HELPER_CALL)
            || str_contains($contents, self::LEGACY_GROUP) && TestFiles::isTest($path)
        );
    }

    public static function of(string $contents, string $path): self
    {
        // The scopes depend on the bytes and on whether the path is a test,
        // so each of the two keeps its own last file.
        $legacy = TestFiles::isTest($path);

        return LastFile::get(
            $legacy ? self::class . ':test' : self::class,
            $contents,
            static fn(): self => new self(self::scan(PhpTokens::of($contents), $legacy)),
        );
    }

    public function covers(Span $span): bool
    {
        foreach ($this->ranges as [$start, $end]) {
            if ($span->start >= $start && $span->start < $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<PhpToken> $tokens
     * @param bool $legacy Whether a `@group legacy` docblock marks a scope.
     *
     * @return list<array{int, int}>
     */
    private static function scan(array $tokens, bool $legacy): array
    {
        $ranges = [];
        $marked = false;
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token->is(T_DOC_COMMENT)) {
                $marked =
                    $marked
                    || $legacy && str_contains($token->text, self::LEGACY_GROUP)
                    || self::deprecates($token->text);
                continue;
            }

            if ($token->is(T_ATTRIBUTE)) {
                $end = PhpTokens::closer($tokens, $i, '[', ']');
                $marked = $marked || PhpTokens::mentions($tokens, $i, $end, self::IGNORE_ATTRIBUTE);
                $i = $end;
                continue;
            }

            if ($token->isIgnorable() || $token->is(self::MODIFIERS)) {
                continue;
            }

            if ($token->is(self::DECLARATIONS)) {
                if ($marked) {
                    $ranges[] = [$token->pos, PhpTokens::bodyEnd($tokens, $i)];
                }

                $marked = false;
                continue;
            }

            $helper = self::helperCall($tokens, $i);
            if ($helper !== null) {
                $ranges[] = [$token->pos, $tokens[$helper]->pos + 1];
            }

            $marked = false;
        }

        return $ranges;
    }

    /**
     * Whether a docblock carries a `@deprecated` tag, by the rule the
     * deprecation checks use for the text.
     */
    private static function deprecates(string $docblock): bool
    {
        return str_contains($docblock, self::DEPRECATED) && DeprecatedTag::text($docblock) !== null;
    }

    /**
     * The index of the token closing a
     * `DeprecationHelper::backwardsCompatibleCall()` argument list, or null
     * when the token at `$index` does not start one.
     *
     * @param list<PhpToken> $tokens
     */
    private static function helperCall(array $tokens, int $index): ?int
    {
        $token = $tokens[$index];
        if (!$token->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
            return null;
        }

        if ($token->text !== self::HELPER_CLASS && !str_ends_with($token->text, '\\' . self::HELPER_CLASS)) {
            return null;
        }

        $call = PhpTokens::next($tokens, $index + 1);
        if ($call === null || !$tokens[$call]->is(T_DOUBLE_COLON)) {
            return null;
        }

        $name = PhpTokens::next($tokens, $call + 1);
        if ($name === null || $tokens[$name]->text !== self::HELPER_CALL) {
            return null;
        }

        $open = PhpTokens::next($tokens, $name + 1);
        if ($open === null || $tokens[$open]->text !== '(') {
            return null;
        }

        return PhpTokens::closer($tokens, $open, '(', ')');
    }
}
