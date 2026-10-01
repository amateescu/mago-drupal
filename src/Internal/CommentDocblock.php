<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Reporting\Safety;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\SourceFile;
use Mago\Sdk\Syntax\Trivia;
use Mago\Sdk\Syntax\TriviaKind;

use function array_key_exists;
use function array_pop;
use function array_shift;
use function count;
use function explode;
use function implode;
use function ltrim;
use function preg_match;
use function preg_replace;
use function rtrim;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strrpos;
use function substr;
use function substr_count;
use function trim;

/**
 * Rewrites the comment above a declaration as a docblock.
 *
 * The fix is potentially unsafe in every case. PHP's reflection returns a
 * docblock but not a comment, so Drupal's annotation discovery, PHPUnit and
 * the analyzers start to read the text once it is a docblock.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class CommentDocblock
{
    private function __construct() {}

    /**
     * The edit that turns the comment into a docblock, or null when the
     * comment may not document the declaration: a trailing comment of the
     * line above, a comment parted from the declaration by a blank line, an
     * empty one, a directive, or one holding `*\/`.
     */
    public static function edit(SourceFile $file, Trivia $comment, int $declarationStart): ?TextEdit
    {
        return self::build($file, $comment, $declarationStart, []);
    }

    /**
     * The same edit for a file comment, which gets an `@file` tag first
     * unless it starts with one. A `//` run counts from the file's first
     * comment down, and needs a blank line below it, without which it is the
     * comment of the code there.
     */
    public static function fileEdit(SourceFile $file, Trivia $first): ?TextEdit
    {
        $last = self::lastOfRun($file, $first);
        if (preg_match('/\G[ \t]*\r?\n[ \t]*\r?\n/', $file->contents, offset: $last->span->end) !== 1) {
            return null;
        }

        $run = self::run($file, $last);
        $head = $run !== null && str_starts_with($run[2][0] ?? '', '@file') ? [] : ['@file'];

        return self::build($file, $last, $last->span->end, $head);
    }

    /**
     * @param list<string> $head Lines that go before the comment's own.
     */
    private static function build(SourceFile $file, Trivia $comment, int $declarationStart, array $head): ?TextEdit
    {
        $run = self::run($file, $comment);
        if ($run === null) {
            return null;
        }

        [$start, $end, $lines] = $run;
        $contents = $file->contents;
        $lineStart = strrpos(substr($contents, offset: 0, length: $start), needle: "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $indent = substr($contents, $lineStart, $start - $lineStart);
        $between = substr($contents, $end, $declarationStart - $end);
        if (trim($indent) !== '' || preg_match('/\n[ \t]*\r?\n/', $between) === 1 || trim(implode('', $lines)) === '') {
            return null;
        }

        $eol = LineEnding::of($contents);
        $body = '';
        foreach ([...$head, ...$lines] as $line) {
            $body .= $line === '' ? "{$indent} *{$eol}" : "{$indent} * {$line}{$eol}";
        }

        return TextEdit::replace(
            new Span($start, $end),
            "/**{$eol}{$body}{$indent} */",
        )->withSafety(Safety::PotentiallyUnsafe);
    }

    /**
     * The comment's span and text lines: a block comment alone, or a run of
     * `//` and `#` lines that ends with the comment.
     *
     * @return array{int, int, list<string>}|null
     */
    private static function run(SourceFile $file, Trivia $comment): ?array
    {
        if ($comment->kind === TriviaKind::MultiLineComment) {
            if (Docblocks::isDirective($file, $comment)) {
                return null;
            }

            return [$comment->span->start, $comment->span->end, self::blockLines($file->getText($comment->span))];
        }

        if ($comment->kind !== TriviaKind::SingleLineComment && $comment->kind !== TriviaKind::HashComment) {
            return null;
        }

        return self::lineRun($file, $comment);
    }

    /**
     * The run of `//` and `#` lines that ends with the comment, one line
     * apart each.
     *
     * @return array{int, int, list<string>}|null
     */
    private static function lineRun(SourceFile $file, Trivia $comment): ?array
    {
        $trivia = $file->getTrivia();
        $first = self::indexOf($trivia, $comment);
        if ($first === null) {
            return null;
        }

        $last = $first;
        while ($first > 0 && self::continues($file, $trivia[$first - 1], $trivia[$first])) {
            $first--;
        }

        $lines = [];
        for ($position = $first; $position <= $last; $position++) {
            $line = $trivia[$position];
            $text = rtrim($file->getText($line->span));
            if (Docblocks::isDirective($file, $line) || str_contains($text, '*/')) {
                return null;
            }

            $text = substr($text, str_starts_with($text, '#') ? 1 : 2);
            $lines[] = str_starts_with($text, ' ') ? substr($text, offset: 1) : $text;
        }

        return [$trivia[$first]->span->start, $comment->span->end, self::trimEmpty($lines)];
    }

    /**
     * The lines without the empty ones at either end.
     *
     * @param list<string> $lines
     * @return list<string>
     */
    private static function trimEmpty(array $lines): array
    {
        while ($lines !== [] && trim($lines[0]) === '') {
            array_shift($lines);
        }

        while ($lines !== [] && trim($lines[count($lines) - 1]) === '') {
            array_pop($lines);
        }

        return $lines;
    }

    /**
     * The last comment of the `//` run that starts with the comment, or the
     * comment itself.
     */
    private static function lastOfRun(SourceFile $file, Trivia $comment): Trivia
    {
        $trivia = $file->getTrivia();
        $index = self::indexOf($trivia, $comment);
        if ($index === null || $comment->kind === TriviaKind::MultiLineComment) {
            return $comment;
        }

        while (array_key_exists($index + 1, $trivia) && self::continues($file, $trivia[$index], $trivia[$index + 1])) {
            $index++;
        }

        return $trivia[$index];
    }

    /**
     * @param list<Trivia> $trivia
     */
    private static function indexOf(array $trivia, Trivia $comment): ?int
    {
        foreach ($trivia as $position => $candidate) {
            if ($candidate->span->start !== $comment->span->start) {
                continue;
            }

            return $position;
        }

        return null;
    }

    /**
     * Whether the line comment above sits on the line right before the next
     * one, so the two belong to one run.
     */
    private static function continues(SourceFile $file, Trivia $above, Trivia $below): bool
    {
        if ($above->kind !== TriviaKind::SingleLineComment && $above->kind !== TriviaKind::HashComment) {
            return false;
        }

        $gap = substr($file->contents, $above->span->end, $below->span->start - $above->span->end);

        return trim($gap) === '' && substr_count($gap, needle: "\n") === 1;
    }

    /**
     * The text lines of a block comment, without `/*`, `*\/` and the stars
     * at the start of its lines.
     *
     * @return list<string>
     */
    private static function blockLines(string $text): array
    {
        $inner = substr($text, offset: 2, length: strlen($text) - 4);
        $lines = [];
        foreach (explode("\n", $inner) as $line) {
            $line = rtrim(ltrim($line), characters: " \t\r");
            $line = (string) preg_replace('/^\*[ ]?/', replacement: '', subject: $line);
            $lines[] = $line;
        }

        return self::trimEmpty($lines);
    }
}
