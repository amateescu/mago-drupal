<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use PhpToken;

use function array_key_exists;
use function array_values;
use function count;
use function file_get_contents;
use function is_file;
use function str_contains;
use function strtolower;

use const T_ATTRIBUTE;
use const T_FUNCTION;
use const T_STRING;
use const T_TRAIT;

/**
 * The classes and traits declaring a method with `#[TrustedCallback]`, and
 * the names of those methods, read off the PHP files of a Drupal root.
 *
 * The override check needs the classes before analysis starts, as the
 * ancestors of its class-like targets, and metadata is not available then.
 * Those targets follow parents and interfaces, so a trait is kept apart for
 * the caller to swap for the classes using it. An attribute is matched by
 * its short name, so a stray match costs the check a lookup and nothing
 * else: metadata confirms the attribute.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class TrustedCallbackClasses
{
    private const ATTRIBUTE = 'TrustedCallback';

    /**
     * @param list<string> $classes The classes, as written.
     * @param list<string> $methods The methods' names, lowercased.
     * @param list<non-empty-string> $traits The traits, as written.
     */
    private function __construct(
        public readonly array $classes,
        public readonly array $methods,
        public readonly array $traits,
    ) {}

    /**
     * @param list<string> $files
     */
    public static function fromFiles(array $files): self
    {
        $classes = [];
        $methods = [];
        $traits = [];
        foreach ($files as $file) {
            $source = is_file($file) ? file_get_contents($file) : false;
            if ($source === false || !str_contains($source, '#[') || !str_contains($source, self::ATTRIBUTE)) {
                continue;
            }

            $tokens = PhpToken::tokenize($source);
            $ranges = null;
            $declaredTraits = null;
            $count = count($tokens);
            for ($i = 0; $i < $count; $i++) {
                $name = $tokens[$i]->is(T_ATTRIBUTE) ? PhpTokens::next($tokens, $i + 1) : null;
                if ($name === null || ClassNames::short($tokens[$name]->text) !== self::ATTRIBUTE) {
                    continue;
                }

                $ranges ??= ClassLikeRanges::scan($tokens);
                $class = $ranges->at($tokens[$i]->pos);
                $method = self::methodAfter($tokens, $i);
                if ($class === null || $class === '' || $method === null) {
                    continue;
                }

                $methods[strtolower($method)] = strtolower($method);
                $declaredTraits ??= self::traits($tokens);
                if (array_key_exists(strtolower(ClassNames::short($class)), $declaredTraits)) {
                    $traits[strtolower($class)] = $class;
                    continue;
                }

                $classes[strtolower($class)] = $class;
            }
        }

        return new self(array_values($classes), array_values($methods), array_values($traits));
    }

    /**
     * The lowercased short names of the traits the tokens declare.
     *
     * @param list<PhpToken> $tokens
     * @return array<string, true>
     */
    private static function traits(array $tokens): array
    {
        $traits = [];
        foreach ($tokens as $index => $token) {
            $name = $token->is(T_TRAIT) ? PhpTokens::next($tokens, $index + 1) : null;
            if ($name !== null && $tokens[$name]->is(T_STRING)) {
                $traits[strtolower($tokens[$name]->text)] = true;
            }
        }

        return $traits;
    }

    /**
     * The name of the method the attribute at the index belongs to: the one
     * the next `function` keyword declares.
     *
     * @param list<PhpToken> $tokens
     */
    private static function methodAfter(array $tokens, int $index): ?string
    {
        $count = count($tokens);
        for ($i = PhpTokens::closer($tokens, $index, '[', ']') + 1; $i < $count; $i++) {
            if ($tokens[$i]->is(T_FUNCTION)) {
                return DeclarationTokens::functionName($tokens, $i);
            }

            if ($tokens[$i]->text === ';' || $tokens[$i]->text === '{') {
                return null;
            }
        }

        return null;
    }
}
