<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Docblock;

use amateescu\MagoDrupal\Internal\Cache\FileMemo;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\SourceFile;

use function count;
use function in_array;
use function preg_match;
use function preg_match_all;
use function rtrim;
use function strlen;
use function strspn;
use function strtolower;
use function substr;
use function trim;

/**
 * Reads the lines, tags and description paragraphs of a docblock from its
 * raw text. CommentAttachment finds the docblock of a declaration.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class Docblocks
{
    /**
     * The characters of a tag name after its `@`.
     */
    public const TAG_NAME_CHARACTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-';

    /**
     * Splits a docblock into its lines and removes the comment markers.
     *
     * Line 0 loses the opening `/**`, any more stars after it and one space
     * or tab. Coder's tokenizer counts the stars of a banner such as
     * `/*****` as part of the opener, and so does DocblockRows. Every
     * other line loses its leading whitespace, its star and one space or
     * tab, unless the star is the one in the closing `*\/`. The line that
     * holds the closer loses it and the whitespace before it. The closer is
     * the whole run of stars and slashes that ends in `*\/`, as Coder reads
     * it, so a `**\/` closer takes its extra star with it. A trailing
     * `\r` from a CRLF file is removed too. The rest of the line, with its
     * trailing whitespace, is the text. The capture keeps its byte offset.
     */
    private const LINE_PATTERN = '/^(?:\/\*\*\**[ \t]?|[ \t]*\*(?!\/)[ \t]?)?(.*?)(?:[ \t]*[*\/]*\*\/)?\r?$/m';

    /**
     * Tags that mark an example inside another tag's description.
     *
     * Drupal writes these inside a `@param` description. If they are
     * indented with the text, they are part of it. If they are at the star
     * column, they parse as tags of their own. Coder's tokenizer reads them
     * the same way. Coder skips these tags and `@link` when it bounds a
     * param comment. Only the code markers count here. The text after a
     * `@link` is a URL and not prose, so the description before it gets the
     * punctuation check.
     */
    public const EXAMPLE_TAGS = ['code', 'endcode'];

    /**
     * Tags that mark up the text around them. Indented, they stay part of
     * that text.
     */
    private const MARKUP_TAGS = ['code', 'endcode', 'link', 'endlink'];

    /**
     * @var FileMemo<array{list<DocblockLine>, list<DocblockTag>}>|null
     */
    private static ?FileMemo $parseMemo = null;

    /**
     * @var FileMemo<array{list<DocblockLine>, list<DocblockLine>}>|null
     */
    private static ?FileMemo $paragraphMemo = null;

    private function __construct() {}

    /**
     * Whether a docblock has text, and the first of it is not a tag, as in
     * `/** @var int *\/`. An empty docblock doesn't have any.
     */
    public static function hasTextBeforeTags(SourceFile $file, Span $docblock): bool
    {
        foreach (self::lines($file, $docblock) as $line) {
            if (trim($line->text) !== '') {
                return preg_match('/^\s*@[a-zA-Z]/', $line->text) !== 1;
            }
        }

        return false;
    }

    /**
     * The exact `@file` tag of a docblock, and whether the docblock has a
     * tag that differs from it only in case.
     *
     * @return array{Span|null, bool}
     */
    public static function fileTag(SourceFile $file, Span $docblock): array
    {
        $variant = false;
        foreach (self::tags($file, $docblock) as $tag) {
            if ($tag->name !== 'file') {
                continue;
            }

            if ($file->getText($tag->nameSpan) === '@file') {
                return [$tag->nameSpan, $variant];
            }

            $variant = true;
        }

        return [null, $variant];
    }

    /**
     * Returns every physical line inside a docblock, without its markers.
     * Each line keeps its absolute offset for precise reports.
     *
     * Line 0 loses the opening `/**`. The line that holds the closing `*\/`
     * loses that too. Every other line loses its leading `*`.
     *
     * @return list<DocblockLine>
     */
    public static function lines(SourceFile $file, Span $span): array
    {
        return self::parse($file, $span)[0];
    }

    /**
     * Returns the docblock's `@tag` entries, each with its
     * continuation lines.
     *
     * @return list<DocblockTag>
     */
    public static function tags(SourceFile $file, Span $span): array
    {
        return self::parse($file, $span)[1];
    }

    /**
     * Splits the lines before the first `@tag` into the short description
     * and the long description below it. The result doesn't have blank
     * lines between the two, or leading or trailing blank lines.
     *
     * A file docblock writes its description after the `@file` tag, so a
     * docblock that starts with `@file` has its paragraphs there. Text on
     * the `@file` line counts as the first line of the summary. The next tag
     * ends them, as it does in any docblock.
     *
     * The result is kept per docblock, as in `parse()`, since several rules
     * read the paragraphs of the same docblock.
     *
     * @return array{list<DocblockLine>, list<DocblockLine>}
     */
    public static function paragraphs(SourceFile $file, Span $span): array
    {
        self::$paragraphMemo ??= new FileMemo();
        $memo = self::$paragraphMemo;
        $start = $span->start;

        return $memo->has($file->contents, $start)
            ? $memo->value($start)
            : $memo->keep($file->contents, self::splitParagraphs($file, $span), $start);
    }

    /**
     * @return array{list<DocblockLine>, list<DocblockLine>}
     */
    private static function splitParagraphs(SourceFile $file, Span $span): array
    {
        $paragraphs = [[]];
        $lines = self::lines($file, $span);
        $first = null;
        foreach ($lines as $index => $line) {
            $first ??= trim($line->text) === '' ? null : $index;
        }

        foreach ($lines as $index => $line) {
            if ($index === $first && self::isFileTagLine($line->text)) {
                $value = self::fileTagValue($line);
                $paragraphs[0] = $value === null ? [] : [$value];

                continue;
            }

            // Coder reads a `@link` or `@endlink` line right below a line of
            // the long description as part of it.
            $inDescription = count($paragraphs) > 1 && $paragraphs[count($paragraphs) - 1] !== [];
            if (self::isTagLine($line->text, inTag: false) && !($inDescription && self::isLinkLine($line->text))) {
                break;
            }

            if (self::isDirectiveLine($line->text)) {
                continue;
            }

            if (trim($line->text) === '') {
                if ($paragraphs[count($paragraphs) - 1] !== []) {
                    $paragraphs[] = [];
                }

                continue;
            }

            $paragraphs[count($paragraphs) - 1][] = $line;
        }

        return [$paragraphs[0], $paragraphs[1] ?? []];
    }

    /**
     * Parses a docblock once into its lines and its tags.
     *
     * The result is memoized per docblock. Several rules in this extension
     * call `lines()`, `tags()` or `paragraphs()` on the same docblock in one
     * file. Without the cache, each of those calls parses the same docblock
     * again. FileMemo keeps the docblocks of the last file text, so a path
     * linted again with new contents gets a fresh parse. A worker lints the
     * targets of one file in sequence, so the memory holds the docblocks of
     * one file at a time and doesn't grow with the worker.
     *
     * @return array{list<DocblockLine>, list<DocblockTag>}
     */
    private static function parse(SourceFile $file, Span $span): array
    {
        self::$parseMemo ??= new FileMemo();
        $memo = self::$parseMemo;
        $start = $span->start;

        return $memo->has($file->contents, $start)
            ? $memo->value($start)
            : $memo->keep($file->contents, self::parseDocblock($file, $span), $start);
    }

    /**
     * @return array{list<DocblockLine>, list<DocblockTag>}
     */
    private static function parseDocblock(SourceFile $file, Span $span): array
    {
        // One regex call over the whole docblock, instead of a split and two
        // or three calls per line. Every line gives exactly one match, and
        // the capture's byte offset is the text's position in the
        // docblock. The same pass collects the tags.
        $matches = [];
        preg_match_all(self::LINE_PATTERN, $file->getText($span), $matches, flags: PREG_OFFSET_CAPTURE);

        // The stub for preg_match_all() does not model the offset-capture
        // shape, where each match is a value and byte offset pair.
        // @mago-expect analysis:docblock-type-mismatch
        /** @var list<array{string, int}> $pairs */
        $pairs = $matches[1];

        $lines = [];
        $tags = [];
        $name = null;
        $nameSpan = new Span(0, 0);
        $tagLines = [];
        foreach ($pairs as $pair) {
            $text = $pair[0];
            $offset = $span->start + $pair[1];
            $line = new DocblockLine($text, $offset);
            $lines[] = $line;

            if (!self::isTagLine($text, inTag: $name !== null)) {
                if ($name !== null && !self::isDirectiveLine($text)) {
                    $tagLines[] = $line;
                }

                continue;
            }

            if ($name !== null) {
                $tags[] = new DocblockTag($name, $nameSpan, $tagLines);
            }

            // The name runs from the `@` over letters, digits, `_` and `-`.
            // One space or tab after it is also part of the marker.
            $at = strspn($text, characters: " \t");
            $end = $at + 1 + strspn($text, self::TAG_NAME_CHARACTERS, offset: $at + 1);
            $name = strtolower(substr($text, offset: $at + 1, length: $end - $at - 1));
            $nameSpan = new Span($offset + $at, $offset + $end);
            $skip = $end + (($text[$end] ?? '') === ' ' || ($text[$end] ?? '') === "\t" ? 1 : 0);
            $tagLines = [new DocblockLine(substr($text, $skip), $offset + $skip)];
        }

        if ($name !== null) {
            $tags[] = new DocblockTag($name, $nameSpan, $tagLines);
        }

        return [$lines, $tags];
    }

    /**
     * Returns the lines before the first `@tag`. Those are the summary and
     * the long description below it.
     *
     * @return list<DocblockLine>
     */
    public static function leadingLines(SourceFile $file, Span $span): array
    {
        $leading = [];
        foreach (self::lines($file, $span) as $line) {
            if (self::isTagLine($line->text, inTag: false)) {
                break;
            }

            $leading[] = $line;
        }

        return $leading;
    }

    /**
     * Whether a stripped docblock line is the `@file` tag, with or without
     * text after it.
     */
    private static function isFileTagLine(string $text): bool
    {
        return preg_match('/^[ \t]*@file(?:[ \t]|$)/', $text) === 1;
    }

    /**
     * The text after `@file` on its line, or null when there is none.
     */
    private static function fileTagValue(DocblockLine $line): ?DocblockLine
    {
        $skip = strspn($line->text, characters: " \t") + strlen('@file');
        $skip += strspn($line->text, characters: " \t", offset: $skip);
        $value = rtrim(substr($line->text, $skip));

        return $value === '' ? null : new DocblockLine($value, $line->offset + $skip);
    }

    /**
     * Whether a stripped docblock line is a phpcs instruction, such as
     * `phpcs:ignore Drupal.Commenting.FunctionComment.Missing`. It is part of
     * neither a description nor the tag above it. Coder reads a line below
     * the summary the same way.
     */
    private static function isDirectiveLine(string $text): bool
    {
        return preg_match('/^\s*phpcs:(?:ignore|disable|enable|set)\b/', $text) === 1;
    }

    /**
     * Whether a stripped docblock line starts with `@link` or `@endlink`.
     */
    private static function isLinkLine(string $text): bool
    {
        return preg_match('/^\s*@(?:end)?link\b/', $text) === 1;
    }

    /**
     * Whether a stripped docblock line starts a `@tag`.
     *
     * A tag indented by mistake, as in `*  @var`, still counts, as phpcs
     * reads it as a tag too. An indented markup tag such as an `@code`
     * example stays part of the text around it, and so does any tag inside
     * the description of another tag, indented as deep as that description.
     */
    private static function isTagLine(string $text, bool $inTag): bool
    {
        $at = strspn($text, characters: " \t");
        $second = substr($text, offset: $at + 1, length: 1);
        if (($text[$at] ?? '') !== '@' || !($second >= 'a' && $second <= 'z' || $second >= 'A' && $second <= 'Z')) {
            return false;
        }

        if ($at === 0) {
            return true;
        }

        $name = substr($text, offset: $at + 1, length: strspn($text, self::TAG_NAME_CHARACTERS, offset: $at + 1));

        return !($inTag && $at >= 2) && !in_array(strtolower($name), self::MARKUP_TAGS, strict: true);
    }
}
