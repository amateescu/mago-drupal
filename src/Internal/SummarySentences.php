<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;

use function basename;
use function count;
use function in_array;
use function mb_substr;
use function ord;
use function preg_match;
use function rtrim;
use function strlen;
use function strspn;
use function strtolower;
use function strtoupper;

/**
 * The wording checks of Drupal.Commenting.DocComment on the short and the
 * long description, for `drupal/doc-sentence`: a capital letter at the
 * start, terminal punctuation at the end of the summary, and a summary on
 * one line. `drupal/long-description-punctuation` checks the end of the
 * long description, since core's `phpcs.xml.dist` turns that check off.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class SummarySentences
{
    private function __construct() {}

    public static function check(LintContext $context, Span $docblock): void
    {
        [$summary, $description] = Docblocks::paragraphs($context->file, $docblock);
        if ($summary !== []) {
            self::checkShortCapital($context, $summary);
            self::checkSummaryEnd($context, $summary);
            if (count($summary) > 1) {
                $last = $summary[count($summary) - 1];
                $context->report(Issue::new(
                    'A short description must fit on one line. Move the rest to a long description.',
                    new Span($last->offset, $last->offset + strlen(rtrim($last->text))),
                ));
            }
        }

        // Coder reports only a lower-case letter at the start of the long
        // description.
        if ($description !== [] && self::lowerCaseStart($description[0]) !== null) {
            self::reportCapital($context, $description[0], 'long description');
        }
    }

    /**
     * Checks that the short description starts with an upper-case letter.
     *
     * Coder tests the first byte of the summary. A digit, `#`, `_` or any
     * other ASCII character that is not an upper-case letter is reported. A
     * multi-byte first character is not: Coder's Unicode pattern errors on a
     * lone byte of it, so the check never fires. The `{@inheritdoc}` tag
     * alone, and a summary that is the file's own name, as Features exports
     * write, are fine.
     *
     * @param list<DocblockLine> $summary
     */
    private static function checkShortCapital(LintContext $context, array $summary): void
    {
        $content = DocblockLine::joined($summary);
        if ($content === '') {
            return;
        }

        $first = ord($content[0]);
        if (
            $first >= 0x41 && $first <= 0x5A
            || $first >= 0x80
            || $content === '{@inheritdoc}'
            || $content === '{@inheritDoc}'
            || $content === basename($context->file->path)
        ) {
            return;
        }

        self::reportCapital($context, $summary[0], 'short description');
    }

    /**
     * Reports a paragraph whose first character is not a capital letter.
     * Like phpcbf, the fix uppercases a lower-case letter. Any other first
     * character doesn't get a fix.
     */
    private static function reportCapital(LintContext $context, DocblockLine $line, string $label): void
    {
        $start = $line->offset + strspn($line->text, characters: " \t");
        $issue = Issue::new("The {$label} must start with a capital letter.", new Span($start, $start + 1));
        $lower = self::lowerCaseStart($line);
        if ($lower !== null) {
            $issue = $issue->withEdit(TextEdit::replace(new Span($start, $start + 1), strtoupper($lower)));
        }

        $context->report($issue);
    }

    /**
     * Returns the first character of a line's text when it is a lower-case
     * ASCII letter, the only kind that PHP's ucfirst() changes, or null.
     */
    private static function lowerCaseStart(DocblockLine $line): ?string
    {
        $matches = [];

        return preg_match('/^[ \t]*([a-z])/', $line->text, $matches) === 1 ? $matches[1] : null;
    }

    /**
     * Checks that the short description ends in one of a fixed set of
     * terminal marks. `drupal/long-description-punctuation` checks the long
     * description, more loosely.
     *
     * @param list<DocblockLine> $summary
     */
    private static function checkSummaryEnd(LintContext $context, array $summary): void
    {
        $last = $summary[count($summary) - 1];
        // The text is trimmed first. Otherwise a stray trailing space, or a
        // "\r" that the line splitter keeps under CRLF, makes it look
        // unexempt.
        $trimmed = rtrim($last->text);
        $lastChar = mb_substr($trimmed, -1);
        // Coder accepts `{@inheritDoc}` too, and a summary that is the
        // file's own name.
        if (
            strtolower($trimmed) === '{@inheritdoc}'
            || in_array($lastChar, ['.', '!', '?', ')'], strict: true)
            || DocblockLine::joined($summary) === basename($context->file->path)
        ) {
            return;
        }

        $lastCharEnd = $last->offset + strlen($trimmed);
        $issue = Issue::new(
            'The short description must end with terminal punctuation.',
            new Span($lastCharEnd - strlen($lastChar), $lastCharEnd),
        );

        // Like phpcbf, the fix adds a full stop only after a letter or a
        // digit on a one-line summary. A summary over two lines may be
        // missing the blank line before the long description.
        if (count($summary) > 1 || preg_match('/^[a-zA-Z0-9]$/', $lastChar) !== 1) {
            $context->report($issue);

            return;
        }

        $context->report($issue->withEdit(TextEdit::insert($lastCharEnd, '.')));
    }
}
