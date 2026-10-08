<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use PhpToken;

use function array_key_exists;
use function count;
use function explode;
use function in_array;
use function ltrim;
use function strtolower;
use function substr;

use const T_AS;
use const T_CLASS;
use const T_DOLLAR_OPEN_CURLY_BRACES;
use const T_DOUBLE_COLON;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;
use const T_NAMESPACE;
use const T_NEW;
use const T_STRING;
use const T_TRAIT;
use const T_USE;
use const T_WHITESPACE;

/**
 * One walk over a file's tokens for `TraitComposers`, keeping the namespace,
 * the imports and the class-like whose body is open.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class TraitComposerWalk
{
    private const NAMES = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED];

    private string $namespace = '';

    /**
     * Lowercased alias to imported name.
     *
     * @var array<string, string>
     */
    private array $imports = [];

    /**
     * The class-like whose body is open, with whether it is a trait.
     *
     * @var array{non-empty-string, bool}|null
     */
    private ?array $current = null;

    private int $depth = 0;

    private int $bodyDepth = -1;

    /**
     * @var list<array{non-empty-string, bool}>
     */
    private array $found = [];

    /**
     * @param array<string, non-empty-string> $traits Lowercased trait names.
     */
    public function __construct(
        private readonly array $traits,
    ) {}

    /**
     * The class-likes that use one of the traits, each with whether it is a
     * trait itself.
     *
     * @param list<PhpToken> $tokens
     * @return list<array{non-empty-string, bool}>
     */
    public function walk(array $tokens): array
    {
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token->text === '{' || $token->is(T_DOLLAR_OPEN_CURLY_BRACES)) {
                $this->open();
                continue;
            }

            if ($token->text === '}') {
                $this->close();
                continue;
            }

            if ($token->is(T_NAMESPACE)) {
                $next = PhpTokens::next($tokens, $i + 1);
                $this->namespace = $next !== null && $tokens[$next]->is([T_STRING, T_NAME_QUALIFIED])
                    ? $tokens[$next]->text
                    : '';
                continue;
            }

            if ($token->is([T_CLASS, T_TRAIT]) && $this->current === null) {
                $this->declaration($tokens, $i);
                continue;
            }

            if ($token->is(T_USE)) {
                $this->useStatement($tokens, $i);
            }
        }

        return $this->found;
    }

    /**
     * Opens a brace, which is the body of a declared class-like.
     */
    private function open(): void
    {
        $this->depth++;
        if ($this->current !== null && $this->bodyDepth === -1) {
            $this->bodyDepth = $this->depth;
        }
    }

    /**
     * Closes a brace, and the class-like's body with its own.
     */
    private function close(): void
    {
        $this->depth--;
        if ($this->current !== null && $this->depth < $this->bodyDepth) {
            [$this->current, $this->bodyDepth] = [null, -1];
        }
    }

    /**
     * Opens a class or trait declaration, not `Foo::class` or `new class`.
     *
     * @param list<PhpToken> $tokens
     */
    private function declaration(array $tokens, int $index): void
    {
        $before = $index - 1;
        while ($before >= 0 && $tokens[$before]->is(T_WHITESPACE)) {
            $before--;
        }

        if ($before >= 0 && $tokens[$before]->is([T_DOUBLE_COLON, T_NEW])) {
            return;
        }

        $next = PhpTokens::next($tokens, $index + 1);
        $name = $next === null || !$tokens[$next]->is(T_STRING) ? '' : $tokens[$next]->text;
        $declared = ($this->namespace === '' ? '' : $this->namespace . '\\') . $name;
        if ($name !== '' && $declared !== '') {
            $this->current = [$declared, $tokens[$index]->is(T_TRAIT)];
        }
    }

    /**
     * Records an import outside a class-like, or a trait use in a class-like
     * body.
     *
     * @param list<PhpToken> $tokens
     */
    private function useStatement(array $tokens, int $index): void
    {
        if ($this->current === null) {
            $this->import($tokens, $index);
            return;
        }

        if ($this->depth !== $this->bodyDepth) {
            return;
        }

        $count = count($tokens);
        for ($i = $index + 1; $i < $count && !in_array($tokens[$i]->text, [';', '{'], strict: true); $i++) {
            if (!$tokens[$i]->is(self::NAMES) || !array_key_exists($this->resolve($tokens[$i]->text), $this->traits)) {
                continue;
            }

            $this->found[] = $this->current;
            return;
        }
    }

    /**
     * Records `use A\B;` or `use A\B as C;` at namespace level.
     *
     * @param list<PhpToken> $tokens
     */
    private function import(array $tokens, int $index): void
    {
        $next = PhpTokens::next($tokens, $index + 1);
        if ($next === null || !$tokens[$next]->is(self::NAMES)) {
            return;
        }

        $name = ltrim($tokens[$next]->text, characters: '\\');
        $after = PhpTokens::next($tokens, $next + 1);
        $aliasToken = $after !== null && $tokens[$after]->is(T_AS) ? PhpTokens::next($tokens, $after + 1) : null;
        $alias = $aliasToken === null ? ClassNames::short($name) : $tokens[$aliasToken]->text;
        $this->imports[strtolower($alias)] = $name;
    }

    /**
     * The lowercased fully qualified name a name in the file stands for.
     */
    private function resolve(string $name): string
    {
        if ($name[0] === '\\') {
            return strtolower(substr($name, offset: 1));
        }

        $parts = explode(separator: '\\', string: $name, limit: 2);
        $imported = $this->imports[strtolower($parts[0])] ?? null;
        if ($imported !== null) {
            return strtolower(count($parts) === 1 ? $imported : $imported . '\\' . $parts[1]);
        }

        return strtolower($this->namespace === '' ? $name : $this->namespace . '\\' . $name);
    }
}
