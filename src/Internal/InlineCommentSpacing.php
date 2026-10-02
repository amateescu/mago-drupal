<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Trivia;
use Mago\Sdk\Syntax\TriviaKind;

use function in_array;
use function preg_match;
use function preg_split;
use function rtrim;
use function str_repeat;
use function strlen;
use function strpos;
use function strrpos;
use function strspn;
use function substr;
use function substr_count;
use function trim;

/**
 * The whitespace checks of Drupal.Commenting.InlineComment: one space
 * between `//` and the text, no tab, and more only to continue a list item
 * or a `@todo` on the line above, for `drupal/inline-comment`, and no blank
 * line below a comment on its own line, for
 * `drupal/inline-comment-blank-line`.
 *
 * Each `//` line gets the space check on its own, as Coder does. The blank
 * line check goes by Coder's runs: `//` lines one below the other with
 * nothing else between them, up to a line that starts with `@`. A comment
 * after a `}` on its line, an `@code` example in `//` lines, and a `phpcs:`
 * instruction are skipped.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class InlineCommentSpacing
{
    private function __construct() {}

    /**
     * Checks the space between `//` and the text of each line.
     */
    public static function checkSpaces(LintContext $context): void
    {
        self::walk($context, spaces: true);
    }

    /**
     * Checks the blank line below each run.
     */
    public static function checkBlankLines(LintContext $context): void
    {
        self::walk($context, spaces: false);
    }

    /**
     * Reads the comments the way Coder does, and runs the space check, or
     * the blank line check when $spaces is false.
     *
     * @mago-expect lint:no-boolean-flag-parameter
     */
    private static function walk(LintContext $context, bool $spaces): void
    {
        $contents = $context->file->contents;
        $previousEnd = null;
        // The text of the comment line above, or null for a `phpcs:` line,
        // which phpcs does not count as a comment.
        $previousText = null;
        // Whether walking up the comment lines from the previous one reaches
        // `// @code` before `// @endcode` or a line with no comment.
        $inExample = false;
        // The run the previous comment belongs to: its last line, whether
        // its first line is alone on its line, and whether it has any text.
        $run = null;
        foreach ($context->file->getTrivia() as $trivia) {
            if ($trivia->kind === TriviaKind::DocBlockComment) {
                continue;
            }

            $text = rtrim($context->file->getText($trivia->span), characters: "\r");
            $adjacent = $previousText !== null && self::onNextLine($contents, (int) $previousEnd, $trivia);
            $example = $text === '// @code' || $adjacent && $inExample;
            $directive = preg_match('/^[\s\/*#]*(?:@?phpcs:|@codingStandardsIgnore)/i', $text) === 1;
            $above = $adjacent ? $previousText : null;
            $inExample = match (true) {
                $text === '// @code' => true,
                $directive, $text === '// @endcode' => false,
                default => $adjacent && $inExample,
            };
            $previousEnd = $trivia->span->end;
            $previousText = $directive ? null : self::lastLine($text);
            $isLine = $trivia->kind === TriviaKind::SingleLineComment && !$directive;
            $hasText = trim(substr($text, offset: 2)) !== '';

            $run = $run !== null && $isLine && self::continuesRun($contents, $run[0], $trivia, $text)
                ? [$trivia, $run[1], $run[2] || $hasText]
                : self::endRun($context, $run, $spaces);
            if (!$isLine || $example || self::followsClosingBrace($contents, $trivia->span->start)) {
                continue;
            }

            $run ??= [$trivia, self::aloneOnLine($contents, $trivia->span->start), $hasText];
            if ($spaces) {
                self::checkLine($context, $trivia, rtrim($text), $above);
            }
        }

        self::endRun($context, $run, $spaces);
    }

    /**
     * Whether the comment is on the line right below the offset.
     */
    private static function onNextLine(string $contents, int $offset, Trivia $trivia): bool
    {
        return substr_count($contents, needle: "\n", offset: $offset, length: $trivia->span->start - $offset) === 1;
    }

    /**
     * Checks the blank line below a run that has ended, unless the walk runs
     * the space check.
     *
     * @param array{Trivia, bool, bool}|null $run
     * @return null
     *
     * @mago-expect lint:no-boolean-flag-parameter
     */
    private static function endRun(LintContext $context, ?array $run, bool $spaces): ?array
    {
        if (!$spaces && $run !== null) {
            self::checkBelow($context, $run);
        }

        return null;
    }

    /**
     * Whether the comment continues the run that ends with $last: it is on
     * the next line with nothing before it, and does not start with `@`.
     */
    private static function continuesRun(string $contents, Trivia $last, Trivia $trivia, string $text): bool
    {
        $gap = substr($contents, $last->span->end, $trivia->span->start - $last->span->end);

        return trim($gap) === '' && substr_count($gap, needle: "\n") === 1 && preg_match('#^//\s*@#', $text) !== 1;
    }

    /**
     * Reports a blank line below a run of comment lines, unless the run
     * shares its first line with code, holds no text, or comes right before
     * a docblock. A blank line before a closing bracket is the formatter's.
     * It removes the line before most brackets and adds one before the
     * closing brace of a class-like with members.
     *
     * @param array{Trivia, bool, bool} $run
     */
    private static function checkBelow(LintContext $context, array $run): void
    {
        [$last, $aloneOnLine, $hasText] = $run;
        $contents = $context->file->contents;
        $end = $last->span->end;
        $next = $end + strspn($contents, characters: " \t\r\n", offset: $end);
        if (
            !$aloneOnLine
            || !$hasText
            || $next >= strlen($contents)
            || in_array($contents[$next], ['}', ']', ')'], strict: true)
            || substr($contents, $next, length: 3) === '/**' && substr($contents, $next, length: 4) !== '/**/'
        ) {
            return;
        }

        $gap = substr($contents, $end, $next - $end);
        $first = (int) strpos($gap, needle: "\n");
        $lastBreak = (int) strrpos($gap, needle: "\n");
        if ($first === $lastBreak) {
            return;
        }

        $context->report(Issue::new(
            'Remove the blank line below the comment.',
            $last->span,
        )->withEdit(TextEdit::delete(new Span($end + $first + 1, $end + $lastBreak + 1))));
    }

    /**
     * Whether only spaces come before the offset on its line.
     */
    private static function aloneOnLine(string $contents, int $offset): bool
    {
        $position = $offset - 1;
        while ($position >= 0 && ($contents[$position] === ' ' || $contents[$position] === "\t")) {
            $position--;
        }

        return $position < 0 || $contents[$position] === "\n";
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
