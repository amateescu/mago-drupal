<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\InlineCommentRuns;
use amateescu\MagoDrupal\Internal\InlineCommentSpacing;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\TriviaKind;

use function preg_match;

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
 * Not ported: the ban on a docblock in the middle of a statement. Mago's own
 * `no-empty-comment` rule already reports an empty comment.
 */
final class InlineCommentRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/inline-comment',
            name: 'Inline comment',
            description: 'Checks that a `//` comment has one space after `//`, starts with a capital letter, and does not use `#`.',
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
            if ($words !== [] && preg_match('/^[a-z]+$/', $words[0]) === 1) {
                $context->report(Issue::new('Start an inline comment with a capital letter.', $run[0]->span));
            }
        }

        InlineCommentSpacing::checkSpaces($context);
    }
}
