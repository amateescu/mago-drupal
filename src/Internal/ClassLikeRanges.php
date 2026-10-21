<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Metadata\ClassLikeMetadata;
use Mago\Sdk\Span;
use PhpToken;

use function count;
use function preg_match;

/**
 * The byte range and full name of each class-like in one file.
 *
 * An anonymous class has a range but no name, so a method inside one doesn't
 * belong to a named class-like.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class ClassLikeRanges
{
    private const CLASS_LIKES = [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM];

    /**
     * A class-like declaration where a statement can start: at the start of
     * a line, or after a `;`, a brace, an attribute's `]` or the open tag.
     * Comments may sit before it and between its tokens.
     */
    private const DECLARES =
        '/(?:^|[;{}\]]|<\?php)'
            . SourceText::GAP
            . '(?:(?:abstract|final|readonly)(?![\w\x80-\xff])'
            . SourceText::GAP
            . ')*+(?:class|interface|trait|enum)(?![\w\x80-\xff])'
            . SourceText::GAP
            . '[A-Za-z_\x80-\xff]/mi';

    /**
     * `new class`, with any attributes or `readonly` before `class`.
     */
    private const ANONYMOUS =
        '/\bnew(?![\w\x80-\xff])' . SourceText::GAP . '(?:#\[|(?:readonly|class)(?![\w\x80-\xff]))/i';

    /**
     * @var FileMemo<self>|null
     */
    private static ?FileMemo $memo = null;

    /**
     * @param list<array{int, int, ?string}> $ranges Start and end offsets and
     *   the full name, in source order.
     */
    private function __construct(
        private readonly array $ranges,
    ) {}

    /**
     * The class-like declared around the span, read off the file's tokens,
     * or null when there is none or the codebase does not know it.
     */
    public static function classAt(Codebase $codebase, string $contents, Span $span): ?ClassLikeMetadata
    {
        // Most procedural files don't declare anything, and don't need any
        // tokenizing to say so.
        if (!self::mayDeclare($contents)) {
            return null;
        }

        $name = self::of($contents)->at($span->start);

        return $name === null ? null : $codebase->getClassLike($name);
    }

    /**
     * Whether the text can declare a named class-like. A comment or a string
     * that looks like a declaration passes too, and only costs the tokenizing.
     */
    public static function mayDeclare(string $contents): bool
    {
        return preg_match(self::DECLARES, $contents) === 1;
    }

    /**
     * Whether the text can hold an anonymous class, checked before the file
     * is tokenized for one.
     */
    public static function mayHoldAnonymous(string $contents): bool
    {
        return preg_match(self::ANONYMOUS, $contents) === 1;
    }

    /**
     * Whether the innermost class-like around the span is an anonymous class.
     */
    public static function inAnonymous(string $contents, Span $span): bool
    {
        $innermost = self::of($contents)->innermost($span->start);

        return $innermost !== null && $innermost[2] === null;
    }

    private static function of(string $contents): self
    {
        self::$memo ??= new FileMemo();

        return self::$memo->get($contents, static fn(): self => self::scan(PhpTokens::of($contents)));
    }

    /**
     * @param list<PhpToken> $tokens
     */
    public static function scan(array $tokens): self
    {
        $ranges = [];
        $namespace = '';
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token->is(T_NAMESPACE)) {
                $namespace = self::namespaceAt($tokens, $i);
                continue;
            }

            // An attribute's arguments can name `class:`, which doesn't
            // declare anything.
            if ($token->is(T_ATTRIBUTE)) {
                $i = PhpTokens::closer($tokens, $i, '[', ']');
                continue;
            }

            // `Foo::class` is a constant, not a declaration, and neither is a
            // `class:` named argument.
            $name = PhpTokens::next($tokens, $i + 1);
            if (
                !$token->is(self::CLASS_LIKES)
                || $tokens[PhpTokens::previous($tokens, $i - 1) ?? $i]->is(T_DOUBLE_COLON)
                || $name !== null && $tokens[$name]->text === ':'
            ) {
                continue;
            }

            $ranges[] = [
                $token->pos,
                PhpTokens::bodyEnd($tokens, $i),
                $name !== null && $tokens[$name]->is(T_STRING) ? $namespace . $tokens[$name]->text : null,
            ];
        }

        return new self($ranges);
    }

    /**
     * The full name of the innermost class-like around the offset, or null
     * when the offset is outside every named one.
     */
    public function at(int $offset): ?string
    {
        return $this->innermost($offset)[2] ?? null;
    }

    /**
     * The innermost range around the offset, or null outside every one.
     *
     * @return array{int, int, ?string}|null
     */
    private function innermost(int $offset): ?array
    {
        $found = null;
        foreach ($this->ranges as $range) {
            if ($offset < $range[0] || $offset >= $range[1]) {
                continue;
            }

            $found = $range;
        }

        return $found;
    }

    /**
     * The namespace a `namespace` keyword declares, with a trailing
     * backslash, or an empty string for the global one.
     *
     * @param list<PhpToken> $tokens
     */
    private static function namespaceAt(array $tokens, int $index): string
    {
        $name = PhpTokens::next($tokens, $index + 1);

        return $name !== null && $tokens[$name]->is([T_STRING, T_NAME_QUALIFIED]) ? $tokens[$name]->text . '\\' : '';
    }
}
