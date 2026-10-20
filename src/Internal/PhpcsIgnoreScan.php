<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function array_fill_keys;
use function array_key_exists;
use function array_keys;
use function array_map;
use function count;
use function explode;
use function is_array;
use function ltrim;
use function rtrim;
use function str_contains;
use function str_starts_with;
use function strpos;
use function strtolower;
use function substr;
use function trim;

/**
 * Works out the lines that a file's phpcs suppression comments cover, the
 * way `Tokenizer::createPositionMap()` and `File::process()` of
 * PHP_CodeSniffer 3 do.
 *
 * Each covered line maps to what it ignores: `.all` for every code, or the
 * codes named by the comments, with an `.except` list of the codes that a
 * `phpcs:enable` turned back on. The code follows phpcs's own closely, quirks
 * included, so a comment covers the same lines here as in phpcs.
 *
 * @internal
 *
 * @phpstan-type Ignored array<string, true|array<string, true>>
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class PhpcsIgnoreScan
{
    public const ALL = '.all';

    public const EXCEPT = '.except';

    /**
     * What the open `phpcs:disable` or `@codingStandardsIgnoreStart` region
     * ignores, or null outside one.
     *
     * @var Ignored|null
     */
    private ?array $ignoring = null;

    /**
     * @var array<int, Ignored>
     */
    private array $lines = [];

    private bool $file = false;

    /**
     * @param list<array{int, string, int}> $tokens
     */
    private function __construct(
        private readonly array $tokens,
        private readonly string $eol,
    ) {}

    /**
     * Whether a comment ignores the whole file, and what each covered line
     * ignores.
     *
     * @return array{bool, array<int, Ignored>}
     */
    public static function scan(string $contents): array
    {
        $scan = new self(PhpcsTokens::of($contents), PhpcsTokens::lineEnding($contents));
        $count = count($scan->tokens);
        for ($index = 0; $index < $count; $index++) {
            [$kind, $text, $line] = $scan->tokens[$index];
            if ($kind === PhpcsTokens::COMMENT || $kind === PhpcsTokens::DOC_STRING || $kind === PhpcsTokens::DOC_TAG) {
                $scan->comment($index, $text, $line);
            }

            // Every line inside an open region ignores what the region does,
            // unless a comment already set what it ignores.
            if ($scan->ignoring !== null && !array_key_exists($line, $scan->lines)) {
                $scan->lines[$line] = $scan->ignoring;
            }
        }

        return [$scan->file, $scan->lines];
    }

    private function comment(int $index, string $text, int $line): void
    {
        $comment = rtrim(ltrim($text, characters: " \t/*#"), characters: " */\t\r\n");
        if (str_contains($comment, '@codingStandards')) {
            $this->file = $this->file || str_contains($comment, '@codingStandardsIgnoreFile');
            $this->oldStyle($comment, $index, $line);

            return;
        }

        $lower = strtolower($comment);
        if (!str_starts_with($lower, 'phpcs:') && !str_starts_with($lower, '@phpcs:')) {
            return;
        }

        if ($comment[0] === '@') {
            $comment = substr($comment, offset: 1);
            $lower = strtolower($comment);
        }

        // A note after ` --` isn't part of the codes.
        $note = strpos($lower, needle: ' --');
        if ($note !== false) {
            $comment = substr($comment, offset: 0, length: $note);
            $lower = strtolower($comment);
        }

        [$content, $tokens] = PhpcsCommentLine::lineContext($this->tokens, $index, $this->eol);
        if (str_starts_with($lower, 'phpcs:set')) {
            $this->mark($line, $tokens ? $this->lines[$line] ?? null : [self::ALL => true]);

            return;
        }

        if (str_starts_with($lower, 'phpcs:ignorefile')) {
            $this->file = true;

            return;
        }

        if (str_starts_with($lower, 'phpcs:disable')) {
            $this->mark($line, $content ? $this->lines[$line] ?? null : [self::ALL => true]);
            $this->disable(substr($comment, offset: 14));

            return;
        }

        if (str_starts_with($lower, 'phpcs:enable')) {
            if ($this->ignoring === null) {
                return;
            }

            $codes = self::codes(substr($comment, offset: 13));
            $this->ignoring = $codes === null ? null : self::enabled($this->ignoring, $codes);
            $this->mark($line, $content ? $this->ignoring : [self::ALL => true]);

            return;
        }

        if (!str_starts_with($lower, 'phpcs:ignore')) {
            return;
        }

        // On its own line the comment covers the next line, and after code
        // it covers its own.
        $rules = self::rules(substr($comment, offset: 13)) + ($this->ignoring ?? []);
        if (!$content) {
            $this->lines[$line] = [self::ALL => true];
            $line++;
        }

        $this->lines[$line] = $rules;
    }

    /**
     * Handles `@codingStandardsIgnoreStart`, `End` and `Line`.
     */
    private function oldStyle(string $comment, int $index, int $line): void
    {
        $own = PhpcsCommentLine::onOwnLine($this->tokens, $index);
        if ($this->ignoring !== null) {
            if (str_contains($comment, '@codingStandardsIgnoreEnd')) {
                $this->lines[$line] = $own ? [self::ALL => true] : $this->ignoring;
                $this->ignoring = null;
            }

            return;
        }

        if (str_contains($comment, '@codingStandardsIgnoreStart')) {
            $this->ignoring = [self::ALL => true];
            if ($own) {
                $this->lines[$line] = $this->ignoring;
            }

            return;
        }

        if (str_contains($comment, '@codingStandardsIgnoreLine')) {
            $this->lines[$line] = [self::ALL => true];
            if ($own) {
                $this->lines[$line + 1] = [self::ALL => true];
            }
        }
    }

    /**
     * Opens a region, or adds codes to the open one.
     */
    private function disable(string $list): void
    {
        $codes = self::codes($list);
        if ($codes === null) {
            $this->ignoring = [self::ALL => true];

            return;
        }

        $ignoring = $this->ignoring ?? [];
        foreach ($codes as $code) {
            $ignoring[$code] = true;
            $except = $ignoring[self::EXCEPT] ?? null;
            if (!is_array($except)) {
                continue;
            }

            // Disabling a code again takes it off the list of enabled ones.
            $except = self::without($except, $code);
            unset($ignoring[self::EXCEPT]);
            $ignoring += $except === [] ? [] : [self::EXCEPT => $except];
        }

        $this->ignoring = $ignoring;
    }

    /**
     * Sets what a line ignores, or clears it for null.
     *
     * @param Ignored|null $ignored
     */
    private function mark(int $line, ?array $ignored): void
    {
        unset($this->lines[$line]);
        if ($ignored !== null) {
            $this->lines[$line] = $ignored;
        }
    }

    /**
     * What a region ignores once the codes are enabled, or null when it
     * doesn't ignore anything any more. A code that the region ignores
     * through its sniff or standard goes on the `.except` list.
     *
     * @param Ignored $ignoring
     * @param list<string> $codes
     *
     * @return Ignored|null
     */
    private static function enabled(array $ignoring, array $codes): ?array
    {
        $enabled = [];
        foreach ($codes as $code) {
            $enabled[$code] = true;
            $except = $ignoring[self::EXCEPT] ?? null;
            $ignoring = self::without($ignoring, $code);
            if (is_array($except) && array_key_exists(self::EXCEPT, $ignoring)) {
                $ignoring[self::EXCEPT] = self::without($except, $code);
            }
        }

        if ($ignoring === []) {
            return null;
        }

        $except = $ignoring[self::EXCEPT] ?? [];
        $ignoring[self::EXCEPT] = (is_array($except) ? $except : []) + $enabled;

        return $ignoring;
    }

    /**
     * The codes of a comma-separated list, or `.all` when it's empty.
     *
     * @return array<string, true>
     */
    private static function rules(string $list): array
    {
        return array_fill_keys(self::codes($list) ?? [self::ALL], value: true);
    }

    /**
     * The codes of a comma-separated list, or null when the list counts as
     * empty, the way PHP's `empty()` does.
     *
     * @return list<string>|null
     */
    private static function codes(string $list): ?array
    {
        return $list === '' || $list === '0' ? null : array_map(trim(...), explode(separator: ',', string: $list));
    }

    /**
     * The list without the code and the codes under it, as `Drupal.Files`
     * covers `Drupal.Files.LineLength`.
     *
     * @template T
     *
     * @param array<string, T> $codes
     *
     * @return array<string, T>
     */
    private static function without(array $codes, string $code): array
    {
        foreach (array_keys($codes) as $key) {
            if ($key !== $code && !str_starts_with($key, $code . '.')) {
                continue;
            }

            unset($codes[$key]);
        }

        return $codes;
    }
}
