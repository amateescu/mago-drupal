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
 * (capitalization, terminal punctuation) and the ban on `#`-style comments.
 * Consecutive `//` lines with nothing but their own indentation between them
 * are judged as one logical comment, the same way a paragraph wrapped across
 * several lines reads as one sentence rather than several: capitalization is
 * checked against the first line's first word, terminal punctuation against
 * the last line's last word, so a two-line comment does not get flagged for
 * "starting" mid-sentence on its second line. A line starting with `@`
 * (`// @see …`, `// @todo …`) never continues the run above it, the same way
 * Coder's own sniff starts counting fresh there: a reference line reads as
 * its own thing, not a continuation of the sentence before it, and a run
 * that starts with one is itself exempt from the terminal-punctuation check,
 * the same as any run whose first word does not start with a letter at all.
 * A `cspell:`/`spell-checker:` directive anywhere in a run is exempt from
 * that same check, whichever line it is on. Not ported: the ban on a
 * docblock misused mid-statement, and pure blank-line placement, which
 * `mago format` already governs. Empty comments are already reported by
 * Mago's own `no-empty-comment` rule. A directive comment (`@mago-expect`,
 * `phpcs:ignore`, …) is exempt from the wording checks and does not join a
 * run either: it is a machine-readable instruction, not a line of prose, so
 * it can neither be judged as one nor stitched into the sentence next to it.
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
                    'Drupal follows PSR-12, which reserves "#" for shebang lines.',
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
     * Whether $next is the next physical line after $previous, with nothing
     * but its own leading whitespace between them: what makes a run of `//`
     * lines read as one logical comment rather than several unrelated ones.
     */
    private function continuesRun(LintContext $context, Trivia $previous, Trivia $next): bool
    {
        $between = substr($context->file->contents, $previous->span->end, $next->span->start - $previous->span->end);

        return preg_match('/^[ \t]*\n[ \t]*$/', $between) === 1;
    }

    /**
     * Whether a `//` line's own content starts with `@`, which reads as a
     * reference (`@see …`, `@todo …`) rather than a continuation of prose
     * above it.
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

        // A word that mixes in a digit, underscore or punctuation reads as a
        // machine name or code reference rather than prose, so it is exempt
        // from capitalization.
        if (preg_match('/^[a-z]+$/', $words[0]) === 1) {
            $context->report(Issue::new('Inline comments must start with a capital letter.', $first->span));
        }

        // A run whose first word is not even a word (starting with "@", a
        // digit, punctuation, …) or that carries a spell-check directive
        // anywhere in it is exempt from needing terminal punctuation too.
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
                'Inline comments must end in a full-stop, exclamation mark, question mark or colon.',
                $last->span,
            ));
        }
    }
}
