<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\InlineCommentRuns;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Syntax\NodeKind;

use function count;
use function mb_substr;
use function preg_match;
use function rtrim;
use function str_starts_with;
use function strlen;

/**
 * Checks that a `//` inline comment ends with terminal punctuation.
 *
 * Ports `InvalidEndChar` of Drupal.Commenting.InlineComment, which core's
 * `phpcs.xml.dist` turns off. The rule checks the last word of a run, so a
 * comment wrapped over several lines is one sentence. A run whose first
 * word starts with `@`, a digit or punctuation is exempt, and so is a run
 * with a `cspell:` or `spell-checker:` directive on any line. A last word
 * that is a url, a tag or a function call needs no punctuation.
 *
 * The fix appends a full stop to the last line, as phpcbf does.
 */
final class InlineCommentPunctuationRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/inline-comment-punctuation',
            name: 'Inline comment punctuation',
            description: 'Checks that a `//` comment ends with terminal punctuation.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        foreach (InlineCommentRuns::of($context->file) as $run) {
            [$words, $spellDirective] = InlineCommentRuns::words($context->file, $run);
            if ($words === [] || $spellDirective || preg_match('/^\p{L}/u', $words[0]) !== 1) {
                continue;
            }

            $lastWord = $words[count($words) - 1];
            $exempt =
                str_starts_with($lastWord, 'http')
                || str_starts_with($lastWord, '@')
                || preg_match('/[()]/', $lastWord) === 1;
            if ($exempt || preg_match('/[.!?:)]/', mb_substr($lastWord, start: -1)) === 1) {
                continue;
            }

            $last = $run[count($run) - 1]->span;
            $end = $last->start + strlen(rtrim($context->file->getText($last)));

            $context->report(Issue::new(
                'End an inline comment with a full stop, an exclamation mark, a question mark or a colon.',
                $last,
            )->withEdit(TextEdit::insert($end, '.')));
        }
    }
}
