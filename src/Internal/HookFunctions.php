<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function array_key_exists;
use function count;
use function file_get_contents;
use function implode;
use function is_file;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function strlen;
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
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
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
     * An uppercase segment of a documented name, such as `FORM_ID` in
     * `hook_form_FORM_ID_alter`. Uppercase segments in a row form one
     * placeholder.
     */
    private const PLACEHOLDER = '/(?<=_)[A-Z][A-Z0-9]*(?:_[A-Z][A-Z0-9]*)*(?=_|$)/';

    /**
     * What a placeholder stands for in a lowercased function name.
     */
    private const PLACEHOLDER_VALUE = '[a-z0-9_]+';

    /**
     * Lowercased function name to `[@deprecated text, parameter count]`.
     *
     * @var array<string, array{string|null, int}>
     */
    private readonly array $hooks;

    /**
     * The documented names with a placeholder, in file order, as `[pattern,
     * documented name, @deprecated text, length without the placeholders]`.
     *
     * @var list<array{non-empty-string, string, string|null, int}>
     */
    private readonly array $placeholders;

    /**
     * One pattern for the deprecated names among the placeholders, or null
     * when none is deprecated. Most names miss it, so the placeholders are
     * only walked for a name that could be reported.
     *
     * @var non-empty-string|null
     */
    private readonly ?string $deprecatedPlaceholders;

    /**
     * @param array<string, array{string|null, int}> $hooks Documented
     *   function name to `[@deprecated text, parameter count]`. The text is
     *   null when the hook is not deprecated.
     */
    private function __construct(array $hooks)
    {
        $lowercased = [];
        $placeholders = [];
        $deprecated = [];
        foreach ($hooks as $name => $facts) {
            $lowercased[strtolower($name)] = $facts;
            $pattern = strtolower(preg_replace(self::PLACEHOLDER, self::PLACEHOLDER_VALUE, $name) ?? $name);
            if ($pattern === strtolower($name)) {
                continue;
            }

            $literal = strlen(preg_replace(self::PLACEHOLDER, replacement: '', subject: $name) ?? $name);
            $placeholders[] = ['/^' . $pattern . '\z/', $name, $facts[0], $literal];
            if ($facts[0] !== null) {
                $deprecated[] = $pattern;
            }
        }

        $this->hooks = $lowercased;
        $this->placeholders = $placeholders;
        $this->deprecatedPlaceholders = $deprecated === [] ? null : '/^(?:' . implode('|', $deprecated) . ')\z/';
    }

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
                $hooks[$name] = [DeprecatedTag::text($docblock), self::countParameters($parameters)];
            }
        }

        return new self($hooks);
    }

    /**
     * @param array<string, array{string|null, int}> $hooks
     */
    public static function fromDefinitions(array $hooks): self
    {
        return new self($hooks);
    }

    /**
     * The deprecated documentation function a hook function falls under, as
     * `[documented name, @deprecated text]`; null when it is not deprecated
     * or not documented.
     *
     * A documented function of the same name decides, and the name is then
     * handed back as it came. Otherwise the name is matched against the
     * documented names with a placeholder, such as
     * `hook_search_api_query_TAG_alter`. A name can match several: search_api's
     * `hook_search_api_query_foo_view_alter` also matches core's
     * `hook_ENTITY_TYPE_view_alter`. The match with the most text outside its
     * placeholders decides. When several tie, the name counts only if all of
     * them are deprecated, and the first in file order is handed back.
     *
     * @return array{string, string}|null
     */
    public function deprecation(string $function): ?array
    {
        $key = strtolower($function);
        if (array_key_exists($key, $this->hooks)) {
            $text = $this->hooks[$key][0];

            return $text === null ? null : [$function, $text];
        }

        if ($this->deprecatedPlaceholders === null || preg_match($this->deprecatedPlaceholders, $key) !== 1) {
            return null;
        }

        $found = null;
        $longest = -1;
        foreach ($this->placeholders as [$pattern, $documented, $text, $literal]) {
            if ($literal < $longest || preg_match($pattern, $key) !== 1) {
                continue;
            }

            // A longer match replaces the ones found so far, and one of the
            // same length that is not deprecated keeps the name quiet.
            if ($literal > $longest) {
                $longest = $literal;
                $found = $text === null ? false : [$documented, $text];
                continue;
            }

            if ($text === null) {
                $found = false;
            }
        }

        return $found === false ? null : $found;
    }

    /**
     * How many parameters the documentation function of that exact name
     * declares, or null when none is documented.
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
