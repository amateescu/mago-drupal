<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Trivia;
use Mago\Sdk\Syntax\TriviaKind;

use function preg_match;
use function preg_split;
use function rtrim;
use function str_repeat;
use function strlen;
use function strrpos;
use function strspn;
use function substr;
use function substr_count;
use function trim;

/**
 * The space check of Drupal.Commenting.InlineComment, for
 * `drupal/inline-comment`: one space between `//` and the text, no tab, and
 * more only to continue a list item or a `@todo` on the line above.
 *
 * Each `//` line is checked on its own, as Coder does. A comment after a
 * `}` on its line, an `@code` example in `//` lines, and a `phpcs:`
 * instruction are skipped.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class InlineCommentSpacing
{
    private function __construct() {}

    public static function check(LintContext $context): void
    {
        $contents = $context->file->contents;
        $previousEnd = null;
        // The text of the comment line above, or null for a `phpcs:` line,
        // which phpcs does not count as a comment.
        $previousText = null;
        // Whether walking up the comment lines from the previous one reaches
        // `// @code` before `// @endcode` or a line with no comment.
        $inExample = false;
        foreach ($context->file->getTrivia() as $trivia) {
            if ($trivia->kind === TriviaKind::DocBlockComment) {
                continue;
            }

            $text = rtrim($context->file->getText($trivia->span), characters: "\r");
            $adjacent =
                $previousEnd !== null
                && $previousText !== null
                && substr_count(
                    $contents,
                    needle: "\n",
                    offset: $previousEnd,
                    length: $trivia->span->start - $previousEnd,
                ) === 1;
            $example = $text === '// @code' || $adjacent && $inExample;
            $directive = preg_match('/^[\s\/*#]*@?phpcs:/i', $text) === 1;
            $above = $adjacent ? $previousText : null;
            $inExample = match (true) {
                $text === '// @code' => true,
                $directive, $text === '// @endcode' => false,
                default => $adjacent && $inExample,
            };
            $previousEnd = $trivia->span->end;
            $previousText = $directive ? null : self::lastLine($text);

            if (
                $trivia->kind !== TriviaKind::SingleLineComment
                || $example
                || $directive
                || self::followsClosingBrace($contents, $trivia->span->start)
            ) {
                continue;
            }

            self::checkLine($context, $trivia, rtrim($text), $above);
        }
    }

    /**
     * @param ?string $above The text of the comment on the line above.
     */
    private static function checkLine(LintContext $context, Trivia $trivia, string $text, ?string $above): void
    {
        if (trim(substr($text, offset: 2)) === '') {
            return;
        }

        $start = $trivia->span->start;
        $spaces = strspn($text, characters: ' ', offset: 2);
        $tab = ($text[2 + $spaces] ?? '') === "\t";
        if ($tab || $spaces === 0) {
            $context->report(Issue::new(
                $tab
                    ? 'Use one space, not a tab, between // and the comment text.'
                    : 'Put a space between // and the comment text.',
                $trivia->span,
            )->withEdit(TextEdit::replace(new Span($start, $start + strspn($text, characters: "/\t ")), '// ')));

            return;
        }

        if ($spaces === 1) {
            return;
        }

        $expected = $above === null ? 1 : self::listIndent($above, $spaces);
        if ($expected === $spaces) {
            return;
        }

        if ($expected === null) {
            $context->report(Issue::new(
                'Indent the comment text no deeper than the comment line above.',
                $trivia->span,
            ));

            return;
        }

        // The stub for strspn() returns any int, so the count is not known to
        // be positive.
        // @mago-expect analysis:possibly-invalid-argument
        $indent = str_repeat(' ', $expected);
        $context->report(Issue::new(
            $expected === 1
                ? 'Put only one space between // and the comment text.'
                : "Put {$expected} spaces between // and the comment text, to line up with the list item above.",
            $trivia->span,
        )->withEdit(TextEdit::replace(new Span($start + 2, $start + 2 + $spaces), $indent)));
    }

    /**
     * The spaces a comment line needs after `//`, given the comment line
     * above: the ones it has when it is no deeper than that line, or that
     * line has none, and the indent of the text of a list item or a `@todo`
     * on that line. Null when the line goes deeper for no such reason.
     */
    private static function listIndent(string $above, int $spaces): ?int
    {
        $aboveSpaces = strspn($above, characters: ' ', offset: 2);
        if ($spaces <= $aboveSpaces || $aboveSpaces === 0) {
            return $spaces;
        }

        $words = preg_split('/\s+/', $above);
        $word = $words === false ? '' : $words[1] ?? '';
        if ($word === '-' || $word === '@todo') {
            return $aboveSpaces + 2;
        }

        if (preg_match('/^[0-9]+\./', $word) === 1) {
            return $aboveSpaces + strlen($word) + 1;
        }

        return null;
    }

    /**
     * Whether a `}` comes before the offset on its line, with only spaces
     * between, as in `} // end if`.
     */
    private static function followsClosingBrace(string $contents, int $offset): bool
    {
        $position = $offset - 1;
        while ($position >= 0 && ($contents[$position] === ' ' || $contents[$position] === "\t")) {
            $position--;
        }

        return $position >= 0 && $contents[$position] === '}';
    }

    /**
     * The last line of a comment's text.
     */
    private static function lastLine(string $text): string
    {
        $break = strrpos($text, needle: "\n");

        return $break === false ? $text : substr($text, $break + 1);
    }
}
