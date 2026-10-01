<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\LineEnding;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Mago\Sdk\Syntax\Trivia;
use Mago\Sdk\Syntax\TriviaKind;

use function in_array;
use function ltrim;
use function preg_match;
use function rtrim;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strpos;
use function strrpos;
use function substr;
use function trim;

/**
 * Reports a `//` comment on the same line as the statement before it.
 *
 * Ports Drupal.Commenting.PostStatementComment. A comment that describes a
 * statement belongs on its own line above it, not after it. A comment
 * directly after a closing brace is exempt. The ported sniff treats the
 * brace as the end of a block, not as a statement to comment on. A
 * trailing directive (`$x = foo(); // phpcs:ignore Some.Sniff`) is exempt
 * too. It must be on the line of the statement to work. Also, phpcs reads
 * its own annotations as non-comment tokens, so the ported sniff never
 * sees them.
 *
 * The fix moves the comment to its own line above the statement's last
 * line, with that line's indent, as Coder's fixer does. It is left out
 * wherever the move could change what a comment belongs to: a line that
 * opens a block, closes a construct, follows a docblock or another comment,
 * or continues inside a string or a comment, a comment that the next line
 * continues, and a comment that only applies to one line.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class PostStatementCommentRule implements Rule
{
    /**
     * Comments that tools apply to one line only, which a move would point
     * at another line.
     */
    private const LINE_BOUND = '/(?:disable|ignore|suppress)[-_ ]?(?:current[-_]|next[-_])?line|IgnoreLine|NOSONAR|@codeCoverageIgnore/i';

    private const STRINGS = [
        NodeKind::LiteralString,
        NodeKind::DocumentString,
        NodeKind::CompositeString,
        NodeKind::InterpolatedString,
        NodeKind::ShellExecuteString,
    ];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/post-statement-comment',
            name: 'Post-statement comment',
            description: 'Reports a `//` comment on the same line as the statement before it.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $contents = $context->file->contents;
        $strings = null;
        foreach ($context->file->getTrivia() as $trivia) {
            if ($trivia->kind !== TriviaKind::SingleLineComment || Docblocks::isDirective($context->file, $trivia)) {
                continue;
            }

            // A search with a negative offset does not copy the prefix.
            // strrpos() skips the trailing (length - start) bytes and
            // searches only the rest. That is the same range that
            // substr($contents, 0, $start) copies out.
            $lineStart = strrpos($contents, needle: "\n", offset: $trivia->span->start - strlen($contents));
            $lineStart = $lineStart === false ? 0 : $lineStart + 1;

            $before = rtrim(substr($contents, $lineStart, $trivia->span->start - $lineStart));
            if ($before === '' || str_ends_with($before, '}')) {
                continue;
            }

            $issue = Issue::new('Do not put a comment after a statement on the same line.', $trivia->span)->withHelp(
                'Move the comment to its own line above the statement.',
            );
            if (self::movable($context->file, $trivia, $lineStart, $before)) {
                // The strings are read once per file, and only for a file
                // with a comment that is otherwise ready to move.
                $strings ??= self::multilineStrings($context->file);
                foreach (self::move($context->file, $trivia, $lineStart, $before, $strings) as $edit) {
                    $issue = $issue->withEdit($edit);
                }
            }

            $context->report($issue);
        }
    }

    /**
     * Whether the line allows the move, judged from its text and its
     * neighbours: nothing after the comment, no PHP tag, no block opened or
     * construct closed on it, no comment above it that it would end up
     * below, such as an `@phpstan-ignore` for the statement, and none below
     * it that continues it.
     */
    private static function movable(SourceFile $file, Trivia $comment, int $lineStart, string $before): bool
    {
        $contents = $file->contents;
        $lineEnd = strpos($contents, needle: "\n", offset: $comment->span->end);
        $after = $lineEnd === false
            ? substr($contents, $comment->span->end)
            : substr($contents, $comment->span->end, $lineEnd - $comment->span->end);
        if (trim($after) !== '' || preg_match(self::LINE_BOUND, $file->getText($comment->span)) === 1) {
            return false;
        }

        if ($lineStart === 0 && $before === '<?php') {
            return true;
        }

        $code = ltrim($before);

        return !(
            str_contains($before, '<?')
            || str_ends_with($before, '{')
            || in_array($code[0] ?? '', [')', ']', '}'], strict: true)
            || self::isComment(self::lineAt($contents, $lineStart - 1))
            || $lineEnd !== false
            && self::isComment(self::lineAt($contents, $lineEnd + 1))
        );
    }

    /**
     * The edits that move the comment above its line, or none when the line
     * starts inside a comment or a string, or right below a docblock.
     *
     * @param list<Span> $strings
     * @return list<TextEdit>
     */
    private static function move(
        SourceFile $file,
        Trivia $comment,
        int $lineStart,
        string $before,
        array $strings,
    ): array {
        $contents = $file->contents;
        $text = rtrim($file->getText($comment->span));
        $eol = LineEnding::of($contents);

        // The opening tag keeps its line, and the comment gets the next one.
        if ($lineStart === 0 && $before === '<?php') {
            return [TextEdit::replace(new Span(5, $comment->span->start), $eol)];
        }

        if (self::insideOrBelowDocblock($file, $lineStart) || self::inside($strings, $lineStart)) {
            return [];
        }

        $indent = substr($before, offset: 0, length: strlen($before) - strlen(ltrim($before)));

        return [
            TextEdit::insert($lineStart, $indent . $text . $eol),
            TextEdit::delete(new Span($lineStart + strlen($before), $comment->span->end)),
        ];
    }

    /**
     * The line that holds the offset, without its line break.
     */
    private static function lineAt(string $contents, int $offset): string
    {
        if ($offset < 0 || $offset >= strlen($contents)) {
            return '';
        }

        $start = strrpos(substr($contents, offset: 0, length: $offset), needle: "\n");
        $start = $start === false ? 0 : $start + 1;
        $end = strpos($contents, needle: "\n", offset: $offset);

        return rtrim(substr($contents, $start, ($end === false ? strlen($contents) : $end) - $start));
    }

    /**
     * Whether the line is a comment of its own: a `//` or `#` line, or the
     * end of a block comment.
     */
    private static function isComment(string $line): bool
    {
        $line = trim($line);

        return str_starts_with($line, '//') || str_starts_with($line, '#') || str_ends_with($line, '*/');
    }

    /**
     * Whether the line starts inside a block comment or docblock, or right
     * below a docblock, which the moved comment would then separate from its
     * declaration. Comments never overlap, so the last one that starts before
     * the line is the only one to check.
     */
    private static function insideOrBelowDocblock(SourceFile $file, int $lineStart): bool
    {
        $trivia = $file->getTrivia();
        $index = Docblocks::lastTriviaIndexStartingBefore($trivia, $lineStart - 1);
        $last = $index === null ? null : $trivia[$index];
        if ($last === null) {
            return false;
        }

        if ($last->span->end > $lineStart) {
            return true;
        }

        return (
            $last->kind === TriviaKind::DocBlockComment
            && trim(substr($file->contents, $last->span->end, $lineStart - $last->span->end)) === ''
        );
    }

    /**
     * The spans of the strings that hold a line break, such as heredocs.
     *
     * @return list<Span>
     */
    private static function multilineStrings(SourceFile $file): array
    {
        $spans = [];
        foreach (self::STRINGS as $kind) {
            foreach ($file->getNodes($kind) as $string) {
                if (!str_contains($file->getText($string), "\n")) {
                    continue;
                }

                $spans[] = $string->span;
            }
        }

        return $spans;
    }

    /**
     * Whether the offset lies strictly inside one of the spans.
     *
     * @param list<Span> $spans
     */
    private static function inside(array $spans, int $offset): bool
    {
        foreach ($spans as $span) {
            if ($span->start < $offset && $span->end > $offset) {
                return true;
            }
        }

        return false;
    }
}
