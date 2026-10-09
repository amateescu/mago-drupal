<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\InlineCommentRuns;
use amateescu\MagoDrupal\Internal\InlineCommentSpacing;
use amateescu\MagoDrupal\Internal\InlineDocblocks;
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

use function ltrim;
use function preg_match;
use function strlen;
use function strtoupper;
use function substr;

/**
 * Checks the style, spacing and first word of a `//` inline comment.
 *
 * Ports part of Drupal.Commenting.InlineComment: the space after `//`, the
 * capital letter at the start of a comment, and the ban on `#` comments.
 * The rule checks the first word of a run, so a second line that starts in
 * the middle of a sentence is not reported. `drupal/inline-comment-punctuation`
 * checks the end of a comment and `drupal/inline-comment-blank-line` the blank
 * line below it, the two checks of the sniff that core's `phpcs.xml.dist`
 * turns off.
 *
 * The capital letter fix uppercases the first letter, as phpcbf does.
 *
 * A docblock inside a body is reported unless a tag starts it or a declaration
 * follows it, as Coder does. Mago's own `no-empty-comment` rule already reports
 * an empty docblock.
 */
final class InlineCommentRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/inline-comment',
            name: 'Inline comment',
            description: 'Checks that a `//` comment has one space after `//`, starts with a capital letter, and does not use `#`, and that no docblock sits inside code.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        foreach ($context->file->getTrivia() as $trivia) {
            if ($trivia->kind !== TriviaKind::HashComment) {
                continue;
            }

            $context->report(Issue::new('Use "//" for a single-line comment, not "#".', $trivia->span)->withHelp(
                'Drupal follows PSR-12. PSR-12 reserves "#" for shebang lines.',
            ));
        }

        foreach (InlineCommentRuns::of($context->file) as $run) {
            [$words] = InlineCommentRuns::words($context->file, $run);
            // A word that has a digit, an underscore or punctuation in it is a
            // machine name or a code reference, not prose. It is exempt from
            // the capitalization check.
            if ($words === [] || preg_match('/^[a-z]+$/', $words[0]) !== 1) {
                continue;
            }

            $issue = Issue::new('Start an inline comment with a capital letter.', $run[0]->span);
            $edit = self::capitalize($context->file, $run);
            $context->report($edit === null ? $issue : $issue->withEdit($edit));
        }

        InlineCommentSpacing::checkSpaces($context);

        foreach (InlineDocblocks::find($context->file) as $opener) {
            $context->report(Issue::new(
                'Inline doc block comments are not allowed; use "/* Comment */" or "// Comment" instead.',
                $opener,
            ));
        }
    }

    /**
     * Uppercases the first letter of the run's text.
     *
     * @param list<Trivia> $run
     */
    private static function capitalize(SourceFile $file, array $run): ?TextEdit
    {
        foreach ($run as $trivia) {
            $text = substr($file->getText($trivia->span), offset: 2);
            $word = ltrim($text);
            if ($word === '') {
                continue;
            }

            $start = $trivia->span->start + 2 + strlen($text) - strlen($word);

            return TextEdit::replace(new Span($start, $start + 1), strtoupper($word[0]));
        }

        return null;
    }
}
