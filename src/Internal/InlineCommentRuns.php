<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\SourceFile;
use Mago\Sdk\Syntax\Trivia;
use Mago\Sdk\Syntax\TriviaKind;

use function ltrim;
use function preg_match;
use function preg_split;
use function str_starts_with;
use function substr;
use function trim;

/**
 * Groups a file's `//` comments into runs, the logical comments whose
 * wording the rules check.
 *
 * Consecutive `//` lines with only their own indentation between them are
 * one logical comment. A paragraph wrapped across several lines is one
 * sentence, not several. A line that starts with `@` (`// @see …`,
 * `// @todo …`) never continues the run above it. Coder's own sniff starts a
 * new run there too. A reference line is a comment of its own, not a
 * continuation of the sentence before it.
 *
 * A directive comment (`@mago-expect`, `phpcs:ignore`, …) does not join a
 * run. A directive is a machine-readable instruction, not a line of prose,
 * so it cannot be joined to the sentence next to it.
 *
 * A comment after a `}` on its line, as in `} // end if`, is not part of any
 * run. Coder skips it, so the `//` lines below it are a comment of their own.
 *
 * @internal
 */
final class InlineCommentRuns
{
    private function __construct() {}

    /**
     * The runs of the file, in source order.
     *
     * @return list<non-empty-list<Trivia>>
     */
    public static function of(SourceFile $file): array
    {
        $runs = [];
        $run = [];
        $previous = null;
        foreach ($file->getTrivia() as $trivia) {
            if (!self::canJoinRun($file, $trivia)) {
                $runs = self::close($runs, $run);
                $run = [];
                $previous = null;

                continue;
            }

            if (
                $previous !== null
                && (!self::continues($file, $previous, $trivia) || self::isReference($file, $trivia))
            ) {
                $runs = self::close($runs, $run);
                $run = [];
            }

            $run[] = $trivia;
            $previous = $trivia;
        }

        return self::close($runs, $run);
    }

    /**
     * The words of a run, and whether one of its lines holds a spell-check
     * directive such as `cspell:` or `spell-checker:`.
     *
     * @param list<Trivia> $run
     * @return array{list<string>, bool}
     */
    public static function words(SourceFile $file, array $run): array
    {
        $words = [];
        $spellDirective = false;
        foreach ($run as $trivia) {
            $text = trim(substr($file->getText($trivia->span), offset: 2));
            if ($text === '') {
                continue;
            }

            if (preg_match('/(cspell|spell-checker|spellchecker):/i', $text) === 1) {
                $spellDirective = true;
            }

            $split = preg_split('/\s+/', $text);
            foreach ($split === false ? [] : $split as $word) {
                $words[] = $word;
            }
        }

        return [$words, $spellDirective];
    }

    /**
     * @param list<non-empty-list<Trivia>> $runs
     * @param list<Trivia> $run
     * @return list<non-empty-list<Trivia>>
     */
    private static function close(array $runs, array $run): array
    {
        if ($run !== []) {
            $runs[] = $run;
        }

        return $runs;
    }

    /**
     * Whether the trivia is a `//` line that can be part of a run: not a
     * directive, and not after a `}` on its line.
     */
    private static function canJoinRun(SourceFile $file, Trivia $trivia): bool
    {
        return (
            $trivia->kind === TriviaKind::SingleLineComment
            && !Docblocks::isDirective($file, $trivia)
            && !InlineCommentSpacing::followsClosingBrace($file->contents, $trivia->span->start)
        );
    }

    /**
     * Whether $next is on the line directly after $previous, with only its
     * own leading whitespace between them.
     */
    private static function continues(SourceFile $file, Trivia $previous, Trivia $next): bool
    {
        $between = substr($file->contents, $previous->span->end, $next->span->start - $previous->span->end);

        return preg_match('/^[ \t]*\n[ \t]*$/', $between) === 1;
    }

    /**
     * Whether the text of a `//` line starts with `@`.
     */
    private static function isReference(SourceFile $file, Trivia $trivia): bool
    {
        return str_starts_with(ltrim(substr($file->getText($trivia->span), offset: 2)), '@');
    }
}
