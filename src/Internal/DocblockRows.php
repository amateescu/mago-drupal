<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\SourceFile;

use function count;
use function min;
use function preg_match_all;
use function strlen;
use function strrpos;
use function strspn;
use function substr;
use function trim;

use const PREG_OFFSET_CAPTURE;
use const PREG_SET_ORDER;

/**
 * Reads a docblock as physical lines with their offsets, for the checks on
 * its whitespace, and builds the edits that add or remove its blank lines.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 */
final class DocblockRows
{
    private function __construct() {}

    /**
     * The lines of the docblock. The last one holds the `*\/`.
     *
     * The result is kept per docblock until the file changes, since several
     * rules read the same docblock.
     *
     * @return non-empty-list<DocblockRow>
     */
    public static function of(SourceFile $file, Span $span): array
    {
        static $path = '';
        static $memo = [];

        if ($path !== $file->path) {
            $path = $file->path;
            $memo = [];
        }

        return $memo[$span->start] ??= self::parse($file->contents, $span);
    }

    /**
     * The whitespace before the `/**` on its line, or null when other text
     * comes before it.
     */
    public static function indent(SourceFile $file, Span $span): ?string
    {
        $lineStart = strrpos(substr($file->contents, offset: 0, length: $span->start), needle: "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $indent = substr($file->contents, $lineStart, $span->start - $lineStart);

        return trim($indent, characters: " \t") === '' ? $indent : null;
    }

    /**
     * The line up to and including its star, to start a new line with, or
     * null for a line with no star.
     */
    public static function prefix(SourceFile $file, DocblockRow $row): ?string
    {
        return $row->star === null ? null : substr($file->contents, $row->start, $row->star + 1 - $row->start);
    }

    /**
     * The indexes of the lines that start with a tag.
     *
     * @param list<DocblockRow> $rows
     * @return list<int>
     */
    public static function tags(array $rows): array
    {
        $tags = [];
        foreach ($rows as $index => $row) {
            if ($row->tag() === null) {
                continue;
            }

            $tags[] = $index;
        }

        return $tags;
    }

    /**
     * The first line after $after with text on it.
     *
     * @param list<DocblockRow> $rows
     */
    public static function nextContent(array $rows, int $after): ?int
    {
        for ($index = $after + 1; $index < count($rows); $index++) {
            if (!$rows[$index]->isBlank()) {
                return $index;
            }
        }

        return null;
    }

    /**
     * The last line before $before with text on it.
     *
     * @param list<DocblockRow> $rows
     */
    public static function previousContent(array $rows, int $before): ?int
    {
        for ($index = $before - 1; $index >= 0; $index--) {
            if (!$rows[$index]->isBlank()) {
                return $index;
            }
        }

        return null;
    }

    /**
     * The edit that leaves one blank line between two lines of the docblock,
     * or null when there is one already or the lower line has no star to
     * copy for a new one.
     *
     * @param list<DocblockRow> $rows
     */
    public static function oneBlankBetween(SourceFile $file, array $rows, int $above, int $below): ?TextEdit
    {
        $blanks = $below - $above - 1;
        if ($blanks === 1) {
            return null;
        }

        if ($blanks > 1) {
            return TextEdit::delete(new Span($rows[$above + 2]->start, $rows[$below]->start));
        }

        $prefix = self::prefix($file, $rows[$below]);

        return $prefix === null
            ? null
            : TextEdit::insert($rows[$below]->start, $prefix . LineEnding::of($file->contents));
    }

    /**
     * The edit that removes the blank lines between two lines of the
     * docblock.
     *
     * @param list<DocblockRow> $rows
     */
    public static function noBlankBetween(array $rows, int $above, int $below): TextEdit
    {
        return TextEdit::delete(new Span($rows[$above + 1]->start, $rows[$below]->start));
    }

    /**
     * @return non-empty-list<DocblockRow>
     */
    private static function parse(string $contents, Span $span): array
    {
        // One regex call per docblock. Each line gives one match: the indent,
        // a star that is not part of the closer, and the text between the
        // spaces after it and the trailing whitespace, closer and `\r`. The
        // closer is the run of stars and slashes that ends in `*\/`, as Coder
        // reads it, so `**\/` is one closer and not a star and a `*\/`.
        $matches = [];
        preg_match_all(
            '/^[ \t]*(\*(?!\/)(?![*\/]*\*\/\r?$))?[ \t]*(.*?)[ \t]*(?:[*\/]*\*\/)?\r?$/m',
            substr($contents, $span->start, $span->length()),
            $matches,
            flags: PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
        );

        // Each group is a value and byte offset pair, and a star that is not
        // there has the offset -1.
        /** @var non-empty-list<array{array{string, int}, array{string, int}, array{string, int}}> $lines */
        $lines = $matches;

        $rows = [self::opener($span, $lines[0][2][0])];
        foreach ($lines as $index => [$line, $star, $text]) {
            if ($index === 0) {
                continue;
            }

            $textStart = $span->start + $text[1];
            $rows[] = new DocblockRow(
                $span->start + $line[1],
                $star[1] < 0 ? null : $span->start + $star[1],
                $textStart,
                $textStart + strlen($text[0]),
                $text[0],
            );
        }

        return $rows;
    }

    /**
     * The first line, whose text starts after the `/**`, any stars that run
     * on from it, and the spaces after them.
     */
    private static function opener(Span $span, string $line): DocblockRow
    {
        $skip = min(strlen($line), 3 + strspn($line, characters: '*', offset: min(strlen($line), 3)));
        $skip += strspn($line, characters: " \t", offset: $skip);
        $text = substr($line, $skip);

        return new DocblockRow($span->start, null, $span->start + $skip, $span->start + strlen($line), $text);
    }
}
