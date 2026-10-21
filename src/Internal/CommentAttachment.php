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
use function intdiv;
use function max;
use function min;
use function preg_match;
use function strspn;
use function substr;
use function trim;

/**
 * Finds the comment or docblock attached to a declaration.
 *
 * Mago gives the rules a flat list of trivia per file, so this class
 * compares spans to attach a comment to a declaration. Directive comments,
 * such as `// @mago-expect` and `// phpcs:ignore`, don't document anything
 * and are skipped.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class CommentAttachment
{
    /**
     * Leading text that marks a comment as a tooling instruction and not as
     * documentation. The match starts after the comment markers.
     */
    private const DIRECTIVE_PATTERN = '/\G[\/#* \t]*(?:@mago-|@?phpcs:|@codingStandardsIgnore|@phpstan-|@psalm-)/';

    private function __construct() {}

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
     * what to do and doesn't say anything to the reader.
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
     * Whether any part of a comment lies between the offsets. The parser's
     * trivia says where the comments are, so a `//` in a string or a `#[`
     * attribute doesn't count.
     */
    public static function hasCommentBetween(SourceFile $file, int $start, int $end): bool
    {
        if ($end <= $start) {
            return false;
        }

        $trivia = $file->getTrivia();
        $index = self::lastTriviaIndexStartingBefore($trivia, $end - 1);

        return $index !== null && $trivia[$index]->span->end > $start;
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

        // The match starts at the comment's offset, so the contents aren't
        // copied.
        return preg_match(self::DIRECTIVE_PATTERN, $file->contents, offset: $trivia->span->start) === 1;
    }
}
