<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Span;
use Mago\Sdk\Syntax\SourceFile;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

use function basename;
use function explode;
use function is_array;
use function preg_match;
use function preg_quote;
use function str_contains;
use function str_ends_with;
use function strlen;
use function strpos;
use function strtolower;
use function substr;

use const PREG_OFFSET_CAPTURE;

/**
 * Reads a YAML file, such as an `.info.yml` or a `.routing.yml` file.
 *
 * Mago reads a file that has no PHP open tag as text outside PHP, so the
 * linter gives the rules its contents. The setup config adds `yml` to the
 * extensions that Mago reads.
 *
 * @internal
 */
final class YamlFile
{
    private function __construct() {}

    /**
     * Whether the file name ends with $suffix, such as `.info.yml`, in any
     * letter case.
     */
    public static function is(SourceFile $file, string $suffix): bool
    {
        return str_ends_with(strtolower($file->path), $suffix);
    }

    /**
     * The parsed file, or null when it is not a YAML mapping. Coder skips a
     * file that does not parse.
     *
     * @return array<array-key, mixed>|null
     */
    public static function parse(SourceFile $file): ?array
    {
        // One memo slot. Several rules read the same file, and a worker lints
        // the rules of one file in sequence.
        /** @var array{string, array<array-key, mixed>|null}|null $memo */
        static $memo = null;
        if ($memo === null || $memo[0] !== $file->contents) {
            $memo = [$file->contents, self::decode($file->contents)];
        }

        return $memo[1];
    }

    /**
     * The parsed YAML text, or null when it is not a mapping or does not
     * parse. A custom tag, such as `!tagged_iterator` in a services file,
     * parses.
     *
     * @return array<array-key, mixed>|null
     */
    public static function decode(string $text): ?array
    {
        try {
            /** @var mixed $parsed */
            $parsed = Yaml::parse($text, Yaml::PARSE_CUSTOM_TAGS);
        } catch (ParseException) {
            return null;
        }

        return is_array($parsed) ? $parsed : null;
    }

    /**
     * The parsed `.info.yml` file of a module, theme or profile, or null for
     * any other file. Coder skips a name with a dot before `.info.yml`, which
     * a config file can have, and a file without a `type` key.
     *
     * @return array<array-key, mixed>|null
     */
    public static function extensionInfo(SourceFile $file): ?array
    {
        if (!self::is($file, '.info.yml') || str_contains(substr(basename($file->path), offset: 0, length: -9), '.')) {
            return null;
        }

        $info = self::parse($file);

        return ($info['type'] ?? null) === null ? null : $info;
    }

    /**
     * The span of the line of a top-level key, such as `version: 1.0`, or
     * null when the key is not on a line of its own.
     */
    public static function keyLine(SourceFile $file, string $key): ?Span
    {
        $match = [];
        if (
            preg_match(
                '/^' . preg_quote($key, delimiter: '/') . '[ \t]*:[^\r\n]*/m',
                $file->contents,
                $match,
                flags: PREG_OFFSET_CAPTURE,
            ) !== 1
        ) {
            return null;
        }

        // @mago-expect analysis:docblock-type-mismatch
        /** @var array{string, int} $line */
        $line = $match[0];

        return new Span($line[1], $line[1] + strlen($line[0]));
    }

    /**
     * The span of the first line, where Coder reports a key that is missing.
     */
    public static function firstLine(SourceFile $file): Span
    {
        $end = strpos($file->contents, needle: "\n");

        return new Span(0, $end === false ? strlen($file->contents) : $end);
    }

    /**
     * The lines of the file, each with the offset where it starts.
     *
     * @return list<array{string, int}>
     */
    public static function lines(SourceFile $file): array
    {
        $lines = [];
        $offset = 0;
        foreach (explode("\n", $file->contents) as $line) {
            $lines[] = [$line, $offset];
            $offset += strlen($line) + 1;
        }

        return $lines;
    }
}
