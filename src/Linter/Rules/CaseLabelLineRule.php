<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\LineEnding;
use amateescu\MagoDrupal\Internal\SwitchCases;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function strlen;
use function strrpos;
use function strspn;
use function substr;

/**
 * Reports a comment before a `case` or `default` label on the label's line.
 *
 * Ports the ContentBeforeCase and ContentBeforeDefault checks of
 * Squiz.ControlStructures.SwitchDeclaration for the content that `mago
 * format` leaves there. The formatter moves code before a label to a line of
 * its own, but keeps a block comment that ends on the label's line before
 * the first label of a switch. Only a block comment or a docblock can end
 * there, since a `//` comment runs to the end of its line.
 *
 * The fix puts the label on the next line, at the indent of the line where
 * the comment starts. Only whitespace changes, so the fix is safe.
 */
final class CaseLabelLineRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/case-label-line',
            name: 'Case label line',
            description: "Reports a comment before a case or default label on the label's line.",
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Switch],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $contents = $file->contents;
        foreach (SwitchCases::labels($file, $context->node) as $label) {
            $start = $label->span->start;
            $blank = self::blankStart($contents, $start);
            if ($blank < 2 || substr($contents, $blank - 2, length: 2) !== '*/') {
                continue;
            }

            $commentStart = self::commentStart($file, $blank);
            if ($commentStart === null) {
                continue;
            }

            $word = $label->kind === NodeKind::SwitchDefaultCase ? 'default' : 'case';
            $context->report(self::issue($contents, $word, $start, $blank, $commentStart));
        }
    }

    /**
     * The issue at the label's keyword, with the edits that put the label on
     * the next line.
     *
     * `mago format` keeps the label below a block comment only when the
     * comment starts its own line. When code or another comment comes before
     * the comment on its line, the fix moves the comment to the next line
     * too.
     */
    private static function issue(string $contents, string $word, int $start, int $blank, int $commentStart): Issue
    {
        $lineStart = strrpos(substr($contents, offset: 0, length: $commentStart), needle: "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $indent = substr($contents, $lineStart, strspn($contents, characters: " \t", offset: $lineStart));
        $break = LineEnding::of($contents) . $indent;

        $issue = Issue::new(
            "Put the {$word} label on its own line, below the comment before it.",
            new Span($start, $start + strlen($word)),
        )->withEdit(TextEdit::replace(new Span($blank, $start), $break));

        $before = self::blankStart($contents, $commentStart);

        return $before === $lineStart
            ? $issue
            : $issue->withEdit(TextEdit::replace(new Span($before, $commentStart), $break));
    }

    /**
     * The offset where the spaces and tabs right before $offset start. Most
     * labels have only their indent before them, so the walk back is short
     * and doesn't copy anything.
     */
    private static function blankStart(string $contents, int $offset): int
    {
        while ($offset > 0 && ($contents[$offset - 1] === ' ' || $contents[$offset - 1] === "\t")) {
            $offset--;
        }

        return $offset;
    }

    /**
     * The offset where the comment that ends at $end starts, or null when no
     * comment ends there.
     */
    private static function commentStart(SourceFile $file, int $end): ?int
    {
        $trivia = $file->getTrivia();
        $index = Docblocks::lastTriviaIndexStartingBefore($trivia, $end - 1);
        $comment = $index === null ? null : $trivia[$index];

        return $comment !== null && $comment->span->end === $end ? $comment->span->start : null;
    }
}
