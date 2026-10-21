<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Drupal;

use function array_keys;
use function count;
use function file_get_contents;
use function is_array;
use function is_file;
use function ltrim;
use function preg_match;
use function preg_match_all;
use function sort;
use function str_contains;
use function strtolower;

use const PREG_SET_ORDER;

/**
 * Classes whose docblock marks them `@internal`, read off the PHP files of a
 * Drupal root.
 *
 * The internal-parent check needs these names before analysis starts, as the
 * ancestors of its class-like targets, and metadata is not available then.
 * Mago's own `@internal` flag confirms every hit at check time, so a stale
 * or over-eager match here doesn't cost anything but a lookup.
 *
 * @internal
 */
final class InternalClasses
{
    /**
     * A docblock followed by a class declaration, attributes allowed between,
     * multi-line ones included. Final classes cannot be extended and are left
     * out.
     */
    private const DECLARATION = '~/\*\*((?:(?!\*/).)*?)\*/(?:\s*#\[.*?\])*\s*((?:abstract\s+|final\s+|readonly\s+)*)class\s+([A-Za-z_][A-Za-z0-9_]*)~s';

    /**
     * The tag on its own, not the `{@internal}` inline tag.
     */
    private const TAG = '/(?<![\w{])@internal\b/';

    private const NAMESPACE = '/^namespace\s+([A-Za-z_][A-Za-z0-9_\\\\]*)\s*[;{]/m';

    /**
     * @param array<string, non-empty-string> $classes Lowercased name to the
     *   name as written.
     */
    private function __construct(
        private readonly array $classes,
    ) {}

    /**
     * @param list<string> $files
     */
    public static function fromFiles(array $files): self
    {
        $held = [];
        foreach ($files as $file) {
            $held[$file] = self::file($file);
        }

        return self::merge($held);
    }

    /**
     * The internal classes one file declares, lowercased name to the name as
     * written.
     *
     * @return array<string, non-empty-string>
     */
    public static function file(string $file): array
    {
        $source = is_file($file) ? file_get_contents($file) : false;
        $namespace = [];
        if (
            $source === false
            || !str_contains($source, '@internal')
            || preg_match(self::NAMESPACE, $source, $namespace) !== 1
        ) {
            return [];
        }

        $classes = [];
        $matches = [];
        preg_match_all(self::DECLARATION, $source, $matches, PREG_SET_ORDER);
        foreach ($matches as [$_, $docblock, $modifiers, $name]) {
            if (str_contains($modifiers, 'final') || preg_match(self::TAG, $docblock) !== 1) {
                continue;
            }

            $class = ltrim($namespace[1], characters: '\\') . '\\' . $name;
            $classes[strtolower($class)] = $class;
        }

        return $classes;
    }

    /**
     * The index from what file() gave for each file.
     *
     * @param array<string, mixed> $held
     */
    public static function merge(array $held): self
    {
        $classes = [];
        /** @var mixed $one */
        foreach ($held as $one) {
            if (!is_array($one)) {
                continue;
            }

            // The keys are lowercased names, never numeric, so a spread
            // keeps each where it first came.
            /** @var array<string, non-empty-string> $names */
            $names = $one;
            $classes = [...$classes, ...$names];
        }

        return new self($classes);
    }

    /**
     * The names as written, sorted by their lowercased form.
     *
     * @return list<non-empty-string>
     */
    public function names(): array
    {
        $keys = array_keys($this->classes);
        sort($keys);
        $names = [];
        foreach ($keys as $key) {
            $names[] = $this->classes[$key];
        }

        return $names;
    }

    public function count(): int
    {
        return count($this->classes);
    }
}
