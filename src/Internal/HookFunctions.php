<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function array_key_exists;
use function count;
use function file_get_contents;
use function is_file;
use function preg_match_all;
use function preg_replace;
use function str_contains;
use function strtolower;
use function substr_count;
use function trim;

/**
 * What the `hook_*()` documentation functions in `*.api.php` files declare.
 *
 * Read from disk rather than from Mago's metadata, so every worker process
 * has the same facts without a codebase request per hook implementation.
 *
 * @internal
 */
final class HookFunctions
{
    /**
     * A docblock followed by a `hook_*` function signature. The match stops
     * at the end of each docblock, so one that documents something else
     * never reaches the next hook. Core puts `// phpcs:` lines between some
     * docblocks and their function. Nested parentheses in defaults are
     * matched one level deep.
     */
    private const DECLARATION = '/\/\*\*((?:(?!\*\/).)*)\*\/\s*(?:\/\/[^\n]*\s*)*function\s+(hook_[A-Za-z0-9_]+)\s*\(((?:[^()]|\([^()]*\))*)\)/s';

    /**
     * @param array<string, array{bool, int}> $hooks Lowercased function name to
     *   `[deprecated, parameter count]`.
     */
    private function __construct(
        private readonly array $hooks,
    ) {}

    /**
     * @param list<string> $files Paths of `*.api.php` files.
     */
    public static function fromApiFiles(array $files): self
    {
        $hooks = [];
        foreach ($files as $file) {
            $source = is_file($file) ? file_get_contents($file) : false;
            $matches = [];
            if ($source === false || preg_match_all(self::DECLARATION, $source, $matches, PREG_SET_ORDER) === 0) {
                continue;
            }

            foreach ($matches as [$_, $docblock, $name, $parameters]) {
                $hooks[strtolower($name)] = [
                    str_contains($docblock, '@deprecated'),
                    self::countParameters($parameters),
                ];
            }
        }

        return new self($hooks);
    }

    /**
     * @param array<string, array{bool, int}> $hooks
     */
    public static function fromDefinitions(array $hooks): self
    {
        return new self($hooks);
    }

    /**
     * Whether the hook's documentation function is `@deprecated`; null when
     * no such function is documented.
     */
    public function deprecation(string $function): ?bool
    {
        $key = strtolower($function);

        return array_key_exists($key, $this->hooks) ? $this->hooks[$key][0] : null;
    }

    /**
     * How many parameters the documentation function declares, or null when
     * none is documented.
     */
    public function parameterCount(string $function): ?int
    {
        $key = strtolower($function);

        return array_key_exists($key, $this->hooks) ? $this->hooks[$key][1] : null;
    }

    public function count(): int
    {
        return count($this->hooks);
    }

    /**
     * Commas inside array or call defaults do not separate parameters.
     */
    private static function countParameters(string $parameters): int
    {
        $flat = preg_replace('/\[[^\[\]]*\]|\([^()]*\)/', replacement: '', subject: $parameters) ?? $parameters;

        return trim($flat) === '' ? 0 : substr_count($flat, needle: ',') + 1;
    }
}
