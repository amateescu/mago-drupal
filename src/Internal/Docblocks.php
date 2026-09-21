<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\SourceFile;
use Mago\Sdk\Syntax\Trivia;
use Mago\Sdk\Syntax\TriviaKind;

use function array_reverse;
use function count;
use function intdiv;
use function preg_match;
use function preg_match_all;
use function strcspn;
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
     * Splits a docblock into its lines and removes the comment markers.
     *
     * Line 0 loses the opening `/**` and one space or tab after it. Every
     * other line loses its leading whitespace, its star and one space or
     * tab, unless the star is the one in the closing `*\/`. The line that
     * holds the closer loses it and the whitespace before it. A trailing
     * `\r` from a CRLF file is removed too. The rest of the line, with its
     * trailing whitespace, is the text. The capture keeps its byte offset.
     */
    private const TAG_NAME_CHARACTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-';

    private const LINE_PATTERN = '/^(?:\/\*\*[ \t]?|[ \t]*\*(?!\/)[ \t]?)?(.*?)(?:[ \t]*\*\/)?\r?$/m';

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

    private function __construct() {}

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
     * @return array{list<DocblockLine>, list<DocblockLine>}
     */
    public static function paragraphs(SourceFile $file, Span $span): array
    {
        $paragraphs = [[]];
        foreach (self::lines($file, $span) as $line) {
            if (self::isTagLine($line->text)) {
                break;
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

            if (!self::isTagLine($text)) {
                if ($name !== null) {
                    $tagLines[] = $line;
                }

                continue;
            }

            if ($name !== null) {
                $tags[] = new DocblockTag($name, $nameSpan, $tagLines);
            }

            // The name runs from the `@` over letters, digits, `_` and `-`.
            // One space or tab after it is also part of the marker.
            $length = 1 + strspn($text, self::TAG_NAME_CHARACTERS, offset: 1);
            $name = strtolower(substr($text, offset: 1, length: $length - 1));
            $nameSpan = new Span($offset, $offset + $length);
            $skip = $length + (($text[$length] ?? '') === ' ' || ($text[$length] ?? '') === "\t" ? 1 : 0);
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
            if (self::isTagLine($line->text)) {
                break;
            }

            $leading[] = $line;
        }

        return $leading;
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
     * Whether a stripped docblock line starts a `@tag`.
     *
     * An indented tag inside the description of another tag keeps its
     * indent in the text, so it does not count.
     */
    private static function isTagLine(string $text): bool
    {
        if ($text === '' || $text[0] !== '@') {
            return false;
        }

        $second = substr($text, offset: 1, length: 1);

        return $second >= 'a' && $second <= 'z' || $second >= 'A' && $second <= 'Z';
    }
}
