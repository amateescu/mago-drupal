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
use function explode;
use function intdiv;
use function ltrim;
use function preg_match;
use function preg_replace;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use function trim;

/**
 * Maps docblock comments back to the code they sit next to.
 *
 * Mago hands rules a flat list of trivia per file, so the association with a
 * declaration is done here by comparing spans, and a docblock's internal
 * structure (lines, tags) is recovered from its raw text the same way.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class Docblocks
{
    /**
     * Leading text that marks a comment as a tooling instruction rather
     * than documentation, once its comment markers are stripped.
     */
    private const DIRECTIVE_PREFIXES = ['@mago-', 'phpcs:', '@codingStandardsIgnore', '@phpstan-', '@psalm-'];

    private function __construct() {}

    /**
     * Returns the docblock immediately above a declaration.
     *
     * Only whitespace may sit between the two. Anything else means the
     * docblock documents something other than this declaration.
     */
    public static function attachedTo(SourceFile $file, Node $declaration): ?Span
    {
        $closest = self::closest($file, $declaration);

        return $closest !== null && $closest->kind === TriviaKind::DocBlockComment ? $closest->span : null;
    }

    /**
     * Returns the comment or docblock immediately above a declaration,
     * regardless of its kind.
     *
     * Only whitespace, and directive comments such as `// @mago-expect` or
     * `// phpcs:ignore`, may sit between the two. A directive is transparent
     * here: it is tooling instruction, not an attempt to document the
     * declaration, so it can never itself become the answer and it does not
     * break the attachment of whatever real comment sits above it either.
     * Without this, suppressing an unrelated rule on a documented
     * declaration would make this rule mistake the pragma for the
     * declaration's own, wrongly-styled comment.
     */
    public static function closest(SourceFile $file, Node $declaration): ?Trivia
    {
        $trivia = $file->getTrivia();

        // Trivia is in source order and never overlaps, so a binary search
        // finds the last one ending at or before the declaration directly,
        // rather than a linear scan repeated for every declaration in the
        // file: this runs once per class member, function or property.
        $boundary = self::lastTriviaIndexEndingBefore($trivia, $declaration->span->start);
        if ($boundary === null) {
            return null;
        }

        // Walk backward from the boundary. A directive is skipped and
        // collected; the first real comment becomes the candidate. What
        // remains, read back to front, is the directives between the
        // candidate and the declaration, in source order.
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
     * Returns the index of the last trivia entry ending at or before
     * $position, or null if even the first one ends after it.
     *
     * @param list<Trivia> $trivia
     */
    private static function lastTriviaIndexEndingBefore(array $trivia, int $position): ?int
    {
        $low = 0;
        $high = count($trivia) - 1;
        $result = null;

        while ($low <= $high) {
            $mid = intdiv($low + $high, num2: 2);
            if ($trivia[$mid]->span->end > $position) {
                $high = $mid - 1;

                continue;
            }

            $result = $mid;
            $low = $mid + 1;
        }

        return $result;
    }

    /**
     * Whether a comment is a tooling instruction rather than an attempt to
     * document whatever it sits next to.
     *
     * Shared with `InlineCommentRule`, which also has to look past a
     * directive: one sitting inside a run of prose `//` lines would
     * otherwise read as a sentence fragment on its own.
     */
    public static function isDirective(SourceFile $file, Trivia $trivia): bool
    {
        if ($trivia->kind === TriviaKind::DocBlockComment) {
            return false;
        }

        $text = ltrim($file->getText($trivia->span), characters: "/#* \t");

        foreach (self::DIRECTIVE_PREFIXES as $prefix) {
            if (str_starts_with($text, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns every physical line inside a docblock, with its markers
     * stripped and each line's absolute offset kept for precise reporting.
     *
     * Line 0 loses the opening `/**`; whichever line holds the closing `*\/`
     * loses that too; every other line loses its leading `*`.
     *
     * Memoized per docblock span: several rules in this extension call this,
     * or `tags()`/`leadingLines()`/`paragraphs()`, which call this in turn,
     * on the same docblock within one file, and every one of those calls
     * re-ran the same marker-stripping regexes over the same text before
     * this cache existed. One slot per file, cleared when the path changes,
     * matches how `DrupalFile::fromSource()` already caches: a worker lints
     * one file's targets consecutively, so this bounds memory to one file's
     * docblocks at a time rather than growing for the life of the worker.
     *
     * @return list<DocblockLine>
     */
    public static function lines(SourceFile $file, Span $span): array
    {
        static $path = '';
        static $memo = [];

        if ($path !== $file->path) {
            $path = $file->path;
            $memo = [];
        }

        $key = "{$span->start}:{$span->end}";

        return $memo[$key] ??= self::parseLines($file, $span);
    }

    /**
     * @return list<DocblockLine>
     */
    private static function parseLines(SourceFile $file, Span $span): array
    {
        $rawLines = explode("\n", $file->getText($span));
        $lastIndex = count($rawLines) - 1;

        $lines = [];
        $offset = $span->start;
        foreach ($rawLines as $index => $raw) {
            // A CRLF file leaves a trailing "\r" on every line but the last,
            // since only "\n" was split on. Stripped here, before the other
            // markers: left in, it would sit after "*/" and stop the closing
            // marker's "$" anchor from matching, and every line's text would
            // carry a "\r" no caller expects. Stripping only the end leaves
            // $leading, computed below, exactly what it would be without it.
            $withoutReturn = rtrim($raw, characters: "\r");

            // The closing marker comes off before the leading star, or a
            // last line with nothing but "* /" would lose its star to the
            // leading strip and leave a stray "/" behind.
            $line = $index === $lastIndex
                ? preg_replace('/[ \t]*\*\/$/', replacement: '', subject: $withoutReturn) ?? $withoutReturn
                : $withoutReturn;

            [$leading, $text] = $index === 0 ? self::stripOpening($line) : self::stripStar($line);
            $lines[] = new DocblockLine($text, $offset + $leading);
            $offset += strlen($raw) + 1;
        }

        return $lines;
    }

    /**
     * Returns the lines before the first `@tag`, i.e. the summary and any
     * long description below it.
     *
     * @return list<DocblockLine>
     */
    public static function leadingLines(SourceFile $file, Span $span): array
    {
        $leading = [];
        foreach (self::lines($file, $span) as $line) {
            if (preg_match('/^@[A-Za-z]/', $line->text) === 1) {
                break;
            }

            $leading[] = $line;
        }

        return $leading;
    }

    /**
     * Splits the lines before the first `@tag` into the short description
     * and any long description below it, dropping the blank lines that
     * separate the two and the docblock's own leading and trailing blanks.
     *
     * @return array{list<DocblockLine>, list<DocblockLine>}
     */
    public static function paragraphs(SourceFile $file, Span $span): array
    {
        $paragraphs = [[]];
        foreach (self::leadingLines($file, $span) as $line) {
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
     * Returns the docblock's `@tag` entries, each with its continuation
     * lines already attached.
     *
     * Memoized per docblock span, the same way and for the same reason as
     * `lines()`.
     *
     * @return list<DocblockTag>
     */
    public static function tags(SourceFile $file, Span $span): array
    {
        static $path = '';
        static $memo = [];

        if ($path !== $file->path) {
            $path = $file->path;
            $memo = [];
        }

        $key = "{$span->start}:{$span->end}";

        return $memo[$key] ??= self::parseTags($file, $span);
    }

    /**
     * @return list<DocblockTag>
     */
    private static function parseTags(SourceFile $file, Span $span): array
    {
        $tags = [];
        $name = null;
        $nameSpan = new Span(0, 0);
        $lines = [];

        foreach (self::lines($file, $span) as $line) {
            $matches = [];
            if (preg_match('/^@([A-Za-z][A-Za-z0-9_-]*)[ \t]?/', $line->text, $matches) === 1) {
                if ($name !== null) {
                    $tags[] = new DocblockTag($name, $nameSpan, $lines);
                }

                $name = strtolower($matches[1]);
                $nameSpan = new Span($line->offset, $line->offset + 1 + strlen($matches[1]));
                $lines = [new DocblockLine(
                    substr($line->text, strlen($matches[0])),
                    $line->offset + strlen($matches[0]),
                )];
                continue;
            }

            if ($name !== null) {
                $lines[] = $line;
            }
        }

        if ($name !== null) {
            $tags[] = new DocblockTag($name, $nameSpan, $lines);
        }

        return $tags;
    }

    /**
     * Splits a `@param`/`@return`/`@throws`/`@var` tag's content into its
     * type and the text after it.
     *
     * The type is the leading run of non-whitespace, which is how phpDoc
     * writes a single type, a `|`-separated union, or a generic like
     * `array<string>` with no embedded spaces.
     *
     * @return array{?string, string}
     */
    public static function splitType(string $content): array
    {
        $matches = [];
        if (preg_match('/^(\S+)[ \t]*(.*)$/s', $content, $matches) === 1) {
            return [$matches[1], trim($matches[2])];
        }

        return [null, $content];
    }

    /**
     * Returns the leading run of whitespace and comment markers stripped
     * from a docblock's opening line, and the line's remaining text.
     *
     * @return array{int, string}
     */
    private static function stripOpening(string $line): array
    {
        $matches = [];
        if (preg_match('/^\/\*\*[ \t]?/', $line, $matches) === 1) {
            return [strlen($matches[0]), substr($line, strlen($matches[0]))];
        }

        return [0, $line];
    }

    /**
     * Returns the leading run of whitespace and a star stripped from one
     * docblock line, and the line's remaining text.
     *
     * @return array{int, string}
     */
    private static function stripStar(string $line): array
    {
        $matches = [];
        if (preg_match('/^[ \t]*\*[ \t]?/', $line, $matches) === 1) {
            return [strlen($matches[0]), substr($line, strlen($matches[0]))];
        }

        return [0, $line];
    }
}
