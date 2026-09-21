<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Docblocks;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Mago\Sdk\Syntax\Trivia;
use Mago\Sdk\Syntax\TriviaKind;

use function ltrim;
use function max;
use function mb_strlen;
use function preg_match;
use function preg_match_all;
use function rtrim;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strrpos;
use function substr;
use function trim;

/**
 * Reports comment lines longer than 80 characters.
 *
 * Ports Drupal.Files.LineLength. The sniff only measures lines that end in a
 * comment. `mago format` wraps code to the configured width but does not
 * change comment text, so nothing else covers this.
 *
 * The work starts from the long lines, not from the comments. One regex over
 * the source finds every line past the limit in bytes. Each of those lines
 * then looks up the comment that covers it. The cost follows the long lines,
 * not all of the comment text. Only the docblocks that hold a long line get
 * parsed.
 *
 * The exemptions come from the ported sniff and its PHPCS base. They all
 * cover text that breaks if you wrap it: docblock tag lines, `@code`
 * examples, `// @see`-style reference lines, annotation values, and the
 * `Implements hook_foo()` and `Contains ...` lines. A line is also exempt if
 * its last word, alone on a line at the same indent, is still past the
 * limit. That exempts URLs and long class paths.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class CommentLineLengthRule implements Rule
{
    private const LIMIT = 80;

    /**
     * A whole line of more than 80 bytes. The pattern is anchored at the
     * line start. The regex engine then jumps from newline to newline and
     * does not retry at every byte of a short line.
     */
    private const LONG_LINE_PATTERN = '/^.{81,}/m';

    /**
     * Comment markers. The rule removes them to get a line's own text.
     */
    private const MARKERS = "/#* \t";

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/comment-line-length',
            name: 'Comment line length',
            description: 'Reports comment lines longer than 80 characters.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;

        $trivia = $file->getTrivia();
        if ($trivia === []) {
            return;
        }

        // One regex call finds every line past the limit in bytes, with its
        // offset. A line of 80 characters or fewer cannot reach 81 bytes, so
        // the byte check misses nothing.
        $matches = [];
        preg_match_all(self::LONG_LINE_PATTERN, $file->contents, $matches, flags: PREG_OFFSET_CAPTURE);

        // The stub for preg_match_all() does not model the offset-capture
        // shape. In that shape, each match is a pair of value and byte offset.
        // @mago-expect analysis:docblock-type-mismatch
        /** @var list<array{string, int}> $pairs */
        $pairs = $matches[0];
        foreach ($pairs as $pair) {
            $this->checkLine($context, $trivia, $pair[0], $pair[1]);
        }
    }

    /**
     * Checks one line that is past the limit in bytes.
     *
     * @param list<Trivia> $trivia
     */
    private function checkLine(LintContext $context, array $trivia, string $raw, int $start): void
    {
        $file = $context->file;

        $line = rtrim($raw, characters: "\r");
        $length = mb_strlen($line, encoding: 'UTF-8');
        if ($length <= self::LIMIT) {
            return;
        }

        $end = $start + strlen($line);
        $comment = $this->commentEndingLine($file, $trivia, $start, $end);
        if ($comment === null || Docblocks::isDirective($file, $comment)) {
            return;
        }

        // The sniff tests its exemptions against the comment token alone. The
        // rule judges a trailing comment after code on its own text, not on
        // the code before it.
        $from = max($comment->span->start, $start);
        $exempt = $comment->kind === TriviaKind::DocBlockComment
            ? $this->isExemptDocblockLine($file, $comment, $start, $end)
            : $this->isExemptComment(substr($file->contents, $from, $end - $from));
        if ($exempt) {
            return;
        }

        if ($this->isCommentOnly($file, $comment, $start) && $this->isUnbreakable($line)) {
            return;
        }

        $context->report(Issue::new(
            "This comment line is {$length} characters long. The limit is " . self::LIMIT . ' characters.',
            new Span($start, $end),
        ));
    }

    /**
     * Returns the comment that the line ends in. The ported sniff measures
     * that comment. A line with code after a comment's closing marker is a
     * code line. `mago format` handles those.
     *
     * @param list<Trivia> $trivia
     */
    private function commentEndingLine(SourceFile $file, array $trivia, int $start, int $end): ?Trivia
    {
        $index = Docblocks::lastTriviaIndexStartingBefore($trivia, $end);
        if ($index === null) {
            return null;
        }

        $comment = $trivia[$index];
        if ($comment->span->end < $start) {
            return null;
        }

        if ($comment->span->end >= $end) {
            return $comment;
        }

        // The comment closes before the line does. The line only counts as a
        // comment line if only whitespace follows the comment.
        return trim(substr($file->contents, $comment->span->end, $end - $comment->span->end)) === '' ? $comment : null;
    }

    /**
     * Whether a docblock line holds text that you cannot wrap.
     */
    private function isExemptDocblockLine(SourceFile $file, Trivia $comment, int $start, int $end): bool
    {
        $text = null;
        $inExample = false;
        foreach (Docblocks::lines($file, $comment->span) as $line) {
            $candidate = trim($line->text);

            if ($line->offset >= $start && $line->offset <= $end) {
                $text = $candidate;
                break;
            }

            // Only the lines above the measured line show whether it is in
            // an example block.
            if (str_starts_with($candidate, '@code')) {
                $inExample = true;
            }

            if (str_starts_with($candidate, '@endcode')) {
                $inExample = false;
            }
        }

        if ($text === null || $inExample) {
            return true;
        }

        return (
            str_starts_with($text, '@')
            || !str_contains($text, ' ')
            || str_contains($text, '@Translation(')
            || str_contains($text, '- @link')
            || preg_match('/^Contains [a-zA-Z_\\\\.]+$/', $text) === 1
            || preg_match('/^Implements hook_[a-zA-Z0-9_]+\(\)/', $text) === 1
            // An annotation value that names a class or a path. It must stay
            // on one line. Otherwise the annotation does not parse.
            || preg_match('#= ("|\')?\S+[\\\\/]\S+("|\')?,*$#', $text) === 1
        );
    }

    /**
     * Whether one line of a `//`, `#` or `/* *\/` comment holds text that
     * you cannot wrap.
     */
    private function isExemptComment(string $text): bool
    {
        // You cannot wrap a reference line or text without a space in it,
        // such as a URL.
        return (
            preg_match('#^\s*// @.+#', $text) === 1
            || !str_contains(trim($text, characters: self::MARKERS . "\r"), ' ')
        );
    }

    /**
     * Whether the line holds nothing but the comment.
     */
    private function isCommentOnly(SourceFile $file, Trivia $comment, int $start): bool
    {
        if ($comment->span->start <= $start) {
            return true;
        }

        return trim(substr($file->contents, $start, $comment->span->start - $start)) === '';
    }

    /**
     * Whether wrapping the line does not help. The last word, alone on a
     * line at the same indent, is still past the limit. This covers URLs
     * and class paths.
     */
    private function isUnbreakable(string $line): bool
    {
        $text = ltrim(ltrim($line), characters: self::MARKERS);
        $indent = mb_strlen($line, encoding: 'UTF-8') - mb_strlen($text, encoding: 'UTF-8');

        $space = strrpos($text, needle: ' ');
        $tail = $space === false ? $text : substr($text, $space + 1);

        return ($indent + mb_strlen($tail, encoding: 'UTF-8')) > self::LIMIT;
    }
}
