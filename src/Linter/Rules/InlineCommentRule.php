<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Docblocks;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\Trivia;
use Mago\Sdk\Syntax\TriviaKind;

use function count;
use function ltrim;
use function mb_substr;
use function preg_match;
use function preg_split;
use function str_starts_with;
use function substr;
use function trim;

/**
 * Checks the style and wording of a `//` inline comment.
 *
 * Ports part of Drupal.Commenting.InlineComment: the wording checks
 * (capitalization, terminal punctuation) and the ban on `#` comments.
 *
 * Consecutive `//` lines with only their own indentation between them are
 * one logical comment. A paragraph wrapped across several lines is one
 * sentence, not several. The rule checks the capitalization of the first
 * word on the first line. It checks the terminal punctuation of the last
 * word on the last line. A two-line comment is therefore not reported for
 * a second line that starts in the middle of a sentence.
 *
 * A line that starts with `@` (`// @see …`, `// @todo …`) never continues
 * the run above it. Coder's own sniff starts a new run there too. A
 * reference line is a comment of its own, not a continuation of the
 * sentence before it. A run that starts with a reference line is exempt
 * from the terminal-punctuation check. The same exemption applies to a run
 * whose first word does not start with a letter. A run that has a
 * `cspell:` or `spell-checker:` directive on any line is exempt from that
 * check too.
 *
 * Not ported: the ban on a docblock in the middle of a statement, and the
 * placement of blank lines. `mago format` already controls both. Mago's own
 * `no-empty-comment` rule already reports an empty comment.
 *
 * A directive comment (`@mago-expect`, `phpcs:ignore`, …) is exempt from
 * the wording checks. It does not join a run either. A directive is a
 * machine-readable instruction, not a line of prose. The rule cannot judge
 * it as prose, and cannot join it to the sentence next to it.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class InlineCommentRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/inline-comment',
            name: 'Inline comment',
            description: 'Checks that a `//` comment starts with a capital letter, ends with terminal punctuation, and does not use `#`.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        /** @var list<Trivia> $run */
        $run = [];
        $previous = null;

        foreach ($context->file->getTrivia() as $trivia) {
            if ($trivia->kind === TriviaKind::HashComment) {
                $this->checkRun($context, $run);
                $run = [];
                $previous = null;

                $context->report(Issue::new('Use "//" for a single-line comment, not "#".', $trivia->span)->withHelp(
                    'Drupal follows PSR-12. PSR-12 reserves "#" for shebang lines.',
                ));

                continue;
            }

            if ($trivia->kind !== TriviaKind::SingleLineComment || Docblocks::isDirective($context->file, $trivia)) {
                $this->checkRun($context, $run);
                $run = [];
                $previous = null;

                continue;
            }

            $breaksRun =
                $previous !== null
                && (!$this->continuesRun($context, $previous, $trivia) || $this->isAnnotationLine($context, $trivia));
            if ($breaksRun) {
                $this->checkRun($context, $run);
                $run = [];
            }

            $run[] = $trivia;
            $previous = $trivia;
        }

        $this->checkRun($context, $run);
    }

    /**
     * Whether $next is on the line directly after $previous, with only its
     * own leading whitespace between them. That is what makes a run of `//`
     * lines one logical comment instead of several unrelated ones.
     */
    private function continuesRun(LintContext $context, Trivia $previous, Trivia $next): bool
    {
        $between = substr($context->file->contents, $previous->span->end, $next->span->start - $previous->span->end);

        return preg_match('/^[ \t]*\n[ \t]*$/', $between) === 1;
    }

    /**
     * Whether the content of a `//` line starts with `@`. Such a line is a
     * reference (`@see …`, `@todo …`), not a continuation of the prose above
     * it.
     */
    private function isAnnotationLine(LintContext $context, Trivia $trivia): bool
    {
        $text = ltrim(substr($context->file->getText($trivia->span), offset: 2));

        return str_starts_with($text, '@');
    }

    /**
     * @param list<Trivia> $run
     */
    private function checkRun(LintContext $context, array $run): void
    {
        if ($run === []) {
            return;
        }

        $first = null;
        $last = null;
        $words = [];
        $hasSpellDirective = false;
        foreach ($run as $trivia) {
            $first ??= $trivia;
            $last = $trivia;

            $text = trim(substr($context->file->getText($trivia->span), offset: 2));
            if ($text === '') {
                continue;
            }

            if (preg_match('/(cspell|spell-checker|spellchecker):/i', $text) === 1) {
                $hasSpellDirective = true;
            }

            $split = preg_split('/\s+/', $text);
            foreach ($split === false ? [] : $split as $word) {
                $words[] = $word;
            }
        }

        if ($words === [] || $first === null || $last === null) {
            return;
        }

        // A word that has a digit, an underscore or punctuation in it is a
        // machine name or a code reference, not prose. It is exempt from the
        // capitalization check.
        if (preg_match('/^[a-z]+$/', $words[0]) === 1) {
            $context->report(Issue::new('Start an inline comment with a capital letter.', $first->span));
        }

        // A run whose first word starts with "@", a digit or punctuation is
        // exempt from the terminal-punctuation check. A run with a
        // spell-check directive on any line is exempt too.
        if ($hasSpellDirective || preg_match('/^\p{L}/u', $words[0]) !== 1) {
            return;
        }

        $lastWord = $words[count($words) - 1];
        $lastChar = mb_substr($lastWord, start: -1);
        $exempt =
            str_starts_with($lastWord, 'http')
            || str_starts_with($lastWord, '@')
            || preg_match('/[()]/', $lastWord) === 1;
        if (!$exempt && preg_match('/[.!?:)]/', $lastChar) !== 1) {
            $context->report(Issue::new(
                'End an inline comment with a full stop, an exclamation mark, a question mark or a colon.',
                $last->span,
            ));
        }
    }
}
