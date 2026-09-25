<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use PhpToken;

use function count;

/**
 * The byte range and full name of each class-like in one file.
 *
 * An anonymous class has a range but no name, so a method inside one belongs
 * to no named class-like.
 *
 * @internal
 */
final class ClassLikeRanges
{
    private const CLASS_LIKES = [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM];

    /**
     * @param list<array{int, int, ?string}> $ranges Start and end offsets and
     *   the full name, in source order.
     */
    private function __construct(
        private readonly array $ranges,
    ) {}

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

            // `Foo::class` is a constant, not a declaration.
            if (!$token->is(self::CLASS_LIKES) || self::previous($tokens, $i)?->is(T_DOUBLE_COLON) === true) {
                continue;
            }

            $name = PhpTokens::next($tokens, $i + 1);
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
        $found = null;
        foreach ($this->ranges as [$start, $end, $name]) {
            if ($offset < $start || $offset >= $end) {
                continue;
            }

            $found = $name;
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

    /**
     * The last token before `$index` that is not whitespace or a comment.
     *
     * @param list<PhpToken> $tokens
     */
    private static function previous(array $tokens, int $index): ?PhpToken
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            if (!$tokens[$i]->isIgnorable()) {
                return $tokens[$i];
            }
        }

        return null;
    }
}
