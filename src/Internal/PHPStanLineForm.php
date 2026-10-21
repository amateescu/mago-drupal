<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use PhpToken;

use function array_filter;
use function array_values;
use function preg_match_all;
use function str_contains;
use function strlen;
use function strpos;
use function strrpos;
use function substr;

use const T_DOC_COMMENT;

/**
 * The lines a comment's `@phpstan-ignore-line` and
 * `@phpstan-ignore-next-line` tags cover.
 *
 * The lines follow PHPStan's parser. In a `//` or `/* *\/` comment,
 * `@phpstan-ignore-line` covers the comment's last line and
 * `@phpstan-ignore-next-line` the line after it. When the comment holds both,
 * only `@phpstan-ignore-next-line` counts. In a docblock, both forms count
 * from the line that holds the tag, and a `@phpstan-ignore-next-line` that is
 * the docblock's last tag covers the line after the docblock instead.
 *
 * @internal
 */
final class PHPStanLineForm
{
    private const LINE = '@phpstan-ignore-line';

    private const NEXT_LINE = '@phpstan-ignore-next-line';

    /**
     * Any docblock tag, as PHPStan's parser finds them when it looks for the
     * last one.
     */
    private const DOC_TAGS = '~(@(?:[a-z][a-z0-9-\\\\]+:)?[a-z][a-z0-9-\\\\]*+)|(@[a-z_\\\\][a-z0-9_\:\\\\]*[a-z_][a-z0-9_]*)~si';

    private function __construct() {}

    /**
     * Whether a comment holds a line form. PHPStan doesn't read the
     * identifiers of such a comment.
     */
    public static function in(string $comment): bool
    {
        return str_contains($comment, self::LINE) || str_contains($comment, self::NEXT_LINE);
    }

    /**
     * The first and last offset of each line the comment's line forms cover.
     *
     * @return list<array{int, int}>
     */
    public static function lines(string $contents, PhpToken $comment): array
    {
        // A `//` comment stops before its newline and a `/* */` one after its
        // last character, so that character is on the comment's last line.
        $last = $comment->pos + strlen($comment->text) - 1;
        if (!$comment->is(T_DOC_COMMENT)) {
            $line = str_contains($comment->text, self::NEXT_LINE)
                ? self::lineAfter($contents, $last)
                : PHPStanIgnoreTag::lineAround($contents, $last);

            return $line === null ? [] : [$line];
        }

        $lines = [];
        foreach (self::offsets($comment->text, self::LINE) as $at) {
            $lines[] = PHPStanIgnoreTag::lineAround($contents, $comment->pos + $at);
        }

        // The last tag covers the line after the docblock and doesn't count
        // from its own line.
        $text = $comment->text;
        if (self::endsWithNextLine($text)) {
            $lines[] = self::lineAfter($contents, $last);
            $text = substr($text, offset: 0, length: (int) strrpos($text, self::NEXT_LINE));
        }

        foreach (self::offsets($text, self::NEXT_LINE) as $at) {
            $lines[] = self::lineAfter($contents, $comment->pos + $at);
        }

        return array_values(array_filter($lines, static fn(?array $line): bool => $line !== null));
    }

    /**
     * Whether `@phpstan-ignore-next-line` is the docblock's last tag.
     */
    private static function endsWithNextLine(string $docblock): bool
    {
        $matches = [];
        $count = (int) preg_match_all(self::DOC_TAGS, $docblock, $matches);

        return $count > 0 && $matches[0][$count - 1] === self::NEXT_LINE;
    }

    /**
     * The offsets of every occurrence of the tag in the text.
     *
     * @return list<int>
     */
    private static function offsets(string $text, string $tag): array
    {
        $offsets = [];
        $at = strpos($text, $tag);
        while ($at !== false) {
            $offsets[] = $at;
            $at = strpos($text, $tag, $at + 1);
        }

        return $offsets;
    }

    /**
     * The first and last offset of the line after the one holding the
     * offset, or null on the file's last line.
     *
     * @return array{int, int}|null
     */
    private static function lineAfter(string $contents, int $offset): ?array
    {
        $newline = strpos($contents, needle: "\n", offset: $offset);

        return $newline === false ? null : PHPStanIgnoreTag::lineAround($contents, $newline + 1);
    }
}
