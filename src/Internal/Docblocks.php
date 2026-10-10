<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Mago\Sdk\Syntax\Trivia;
use Mago\Sdk\Syntax\TriviaKind;

use function array_reverse;
use function count;
use function in_array;
use function intdiv;
use function max;
use function min;
use function preg_match;
use function preg_match_all;
use function rtrim;
use function strcspn;
use function strlen;
use function strspn;
use function strtolower;
use function substr;
use function trim;

/**
 * Maps docblock comments to the code next to them.
 *
 * Mago gives the rules a flat list of trivia per file, so this class
 * compares spans to attach a docblock to a declaration. It also reads the
 * lines and tags of a docblock from its raw text.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class Docblocks
{
    /**
     * Leading text that marks a comment as a tooling instruction and not as
     * documentation. The match starts after the comment markers.
     */
    private const DIRECTIVE_PATTERN = '/\G[\/#* \t]*(?:@mago-|phpcs:|@codingStandardsIgnore|@phpstan-|@psalm-)/';

    /**
     * The characters of a tag name after its `@`.
     */
    public const TAG_NAME_CHARACTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-';

    /**
     * Splits a docblock into its lines and removes the comment markers.
     *
     * Line 0 loses the opening `/**` and one space or tab after it. Every
     * other line loses its leading whitespace, its star and one space or
     * tab, unless the star is the one in the closing `*\/`. The line that
     * holds the closer loses it and the whitespace before it. The closer is
     * the whole run of stars and slashes that ends in `*\/`, as Coder reads
     * it, so a `**\/` closer takes its extra star with it. A trailing
     * `\r` from a CRLF file is removed too. The rest of the line, with its
     * trailing whitespace, is the text. The capture keeps its byte offset.
     */
    private const LINE_PATTERN = '/^(?:\/\*\*[ \t]?|[ \t]*\*(?!\/)[ \t]?)?(.*?)(?:[ \t]*[*\/]*\*\/)?\r?$/m';

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

    private function __construct() {}

    /**
     * Whether a docblock has text, and the first of it is not a tag, as in
     * `/** @var int *\/`. An empty docblock has none.
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
     * Whether an open tag comes right before the comment at $index of the
     * file's trivia, with only whitespace and other comments between.
     */
    public static function followsOpenTag(SourceFile $file, int $index): bool
    {
        $trivia = $file->getTrivia();
        $contents = $file->contents;
        $position = $trivia[$index]->span->start;
        $previous = $index - 1;
        while (true) {
            while ($position > 0 && strspn($contents[$position - 1], characters: " \t\r\n\v\f") === 1) {
                --$position;
            }

            if ($previous < 0 || $trivia[$previous]->span->end !== $position) {
                break;
            }

            $position = $trivia[$previous]->span->start;
            --$previous;
        }

        return preg_match('/<\?(?:php|=)$/i', substr($contents, max(0, $position - 5), min(5, $position))) === 1;
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
     * Returns the docblock immediately above a declaration.
     *
     * Only whitespace may be between the two. Any other text means
     * that the docblock documents a different declaration.
     */
    public static function attachedTo(SourceFile $file, Node $declaration): ?Span
    {
        $closest = self::closest($file, $declaration);

        return $closest !== null && $closest->kind === TriviaKind::DocBlockComment ? $closest->span : null;
    }

    /**
     * Returns the comment or docblock immediately above a declaration, of
     * any kind.
     *
     * Only whitespace and directive comments may be between the two.
     * `// @mago-expect` and `// phpcs:ignore` are directive comments. A
     * directive is a tooling instruction and does not document the
     * declaration, so it is never the result, and it does not break the
     * attachment of a real comment above it. Without this, a suppression of
     * an unrelated rule on a documented declaration looks like a comment of
     * the wrong style for that declaration.
     */
    public static function closest(SourceFile $file, Node $declaration): ?Trivia
    {
        $trivia = $file->getTrivia();

        // The trivia is in source order and never overlaps, so a binary
        // search finds the last entry that starts at or before the
        // declaration. A linear scan is too slow, because this runs once per
        // class member, function or property. A comment cannot overlap a
        // declaration, so that entry also ends before the declaration.
        $boundary = self::lastTriviaIndexStartingBefore($trivia, $declaration->span->start);
        if ($boundary === null) {
            return null;
        }

        // Walk backward from the boundary. The loop skips and collects each
        // directive. The first real comment becomes the candidate. The
        // collected list, read back to front, holds the directives between
        // the candidate and the declaration in source order.
        $sinceCandidate = [];
        $candidate = null;
        for ($index = $boundary; $index >= 0; --$index) {
            if (self::isDirective($file, $trivia[$index])) {
                $sinceCandidate[] = $trivia[$index];

                continue;
            }

            $candidate = $trivia[$index];
            break;
        }

        if ($candidate === null) {
            return null;
        }

        $cursor = $candidate->span->end;
        foreach (array_reverse($sinceCandidate) as $directive) {
            $between = substr($file->contents, $cursor, $directive->span->start - $cursor);
            if (trim($between) !== '') {
                return null;
            }

            $cursor = $directive->span->end;
        }

        $tail = substr($file->contents, $cursor, $declaration->span->start - $cursor);

        return trim($tail) === '' ? $candidate : null;
    }

    /**
     * Returns the declaration cut to start where its comment is, for
     * `closest()`. The head is tried first, then each attribute list from
     * the last up. The first one with a comment right above it wins. With
     * none, the result is the declaration itself.
     */
    public static function commentAnchor(SourceFile $file, Node $declaration): Node
    {
        $children = $file->getChildren($declaration);
        $head = 0;
        while (($children[$head] ?? null)?->kind === NodeKind::AttributeList) {
            ++$head;
        }

        // Coder's class, function and property comment sniffs walk back
        // from the head over the attribute lists, so a docblock below an
        // attribute list counts too. The first attribute list starts the
        // declaration, so the fallback covers it.
        for ($index = $head; $index > 0; --$index) {
            $anchor = self::anchorAt($declaration, $children[$index]);
            if (self::closest($file, $anchor) !== null) {
                return $anchor;
            }
        }

        return $declaration;
    }

    /**
     * Returns the declaration cut to start at one of its children, so that
     * `closest()` reads the comment above that child.
     */
    public static function anchorAt(Node $declaration, Node $child): Node
    {
        return new Node($declaration->id, $declaration->kind, $child->span, $declaration->parentId);
    }

    /**
     * Whether a comment that is not a directive sits between the offsets. A
     * directive such as `// @mago-expect` or `// phpcs:ignore` tells a tool
     * what to do and says nothing to the reader.
     */
    public static function hasNoteBetween(SourceFile $file, int $start, int $end): bool
    {
        $trivia = $file->getTrivia();
        $index = self::lastTriviaIndexStartingBefore($trivia, $end);
        while ($index !== null && $index >= 0 && $trivia[$index]->span->start >= $start) {
            if (!self::isDirective($file, $trivia[$index])) {
                return true;
            }

            --$index;
        }

        return false;
    }

    /**
     * Returns the index of the last trivia entry that starts at or before
     * $position. Returns null if the first entry starts after it.
     *
     * @param list<Trivia> $trivia
     */
    public static function lastTriviaIndexStartingBefore(array $trivia, int $position): ?int
    {
        $low = 0;
        $high = count($trivia) - 1;
        $result = null;

        while ($low <= $high) {
            $mid = intdiv($low + $high, num2: 2);
            if ($trivia[$mid]->span->start > $position) {
                $high = $mid - 1;

                continue;
            }

            $result = $mid;
            $low = $mid + 1;
        }

        return $result;
    }

    /**
     * Whether a comment is a tooling instruction and not documentation for
     * the code next to it.
     *
     * `InlineCommentRule` shares this method, because it must also skip a
     * directive. Without that, a directive inside a run of prose `//` lines
     * looks like a sentence fragment.
     */
    public static function isDirective(SourceFile $file, Trivia $trivia): bool
    {
        if ($trivia->kind === TriviaKind::DocBlockComment) {
            return false;
        }

        // The match starts at the comment's offset, so no substring is copied.
        return preg_match(self::DIRECTIVE_PATTERN, $file->contents, offset: $trivia->span->start) === 1;
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
     * and the long description below it. The result has no blank lines
     * between the two, and no leading or trailing blank lines.
     *
     * A file docblock writes its description after the `@file` tag, so a
     * docblock that starts with `@file` has its paragraphs there. Text on
     * the `@file` line counts as the first line of the summary. The next tag
     * ends them, as it does in any docblock.
     *
     * @return array{list<DocblockLine>, list<DocblockLine>}
     */
    public static function paragraphs(SourceFile $file, Span $span): array
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

            if (self::isTagLine($line->text, inTag: false)) {
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
     * again. The cache has one slot per file, and a change of path
     * clears it. `DrupalFile::fromSource()` caches the same way. A worker
     * lints the targets of one file in sequence, so the memory holds the
     * docblocks of one file at a time and does not grow with the worker.
     *
     * @return array{list<DocblockLine>, list<DocblockTag>}
     */
    private static function parse(SourceFile $file, Span $span): array
    {
        static $path = '';
        static $memo = [];

        if ($path !== $file->path) {
            $path = $file->path;
            $memo = [];
        }

        return $memo[$span->start] ??= self::parseDocblock($file, $span);
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
     * The lines trimmed and joined with nothing between them, as Coder joins
     * the lines of a short description.
     *
     * @param list<DocblockLine> $lines
     */
    public static function joinedText(array $lines): string
    {
        $text = '';
        foreach ($lines as $line) {
            $text .= trim($line->text);
        }

        return $text;
    }

    /**
     * Returns the position of $tag in $tags.
     *
     * @param list<DocblockTag> $tags
     */
    public static function indexOf(array $tags, DocblockTag $tag): ?int
    {
        foreach ($tags as $index => $candidate) {
            if ($candidate === $tag) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Splits a `@param`, `@return`, `@throws` or `@var` tag's content into
     * its type and the text after it.
     *
     * The type is the leading run of non-whitespace. phpDoc writes a single
     * type, a `|`-separated union and a generic such as `array<string>`
     * without embedded spaces.
     *
     * @return array{?string, string}
     */
    public static function splitType(string $content): array
    {
        $length = strcspn($content, characters: " \t\n\r\v\f");
        if ($length === 0) {
            return [null, $content];
        }

        return [substr($content, offset: 0, length: $length), trim(substr($content, $length))];
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
