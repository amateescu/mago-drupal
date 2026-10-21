<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Docblock;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;

use function ltrim;
use function rtrim;
use function str_starts_with;
use function strlen;
use function strspn;
use function substr;
use function trim;

/**
 * Checks the `@see` tags of a docblock: each holds one reference on its own
 * line, with no other text and no punctuation at the end.
 *
 * Ports the `@see` checks of Drupal.Commenting.FunctionComment, which reads
 * function docblocks only. Drupal's coding standards ask the same of every
 * docblock.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class SeeTags
{
    private function __construct() {}

    /**
     * Runs `checkEmpty()`, then `checkReferences()`.
     *
     * @param list<DocblockTag> $tags
     */
    public static function check(LintContext $context, array $tags): void
    {
        self::checkEmpty($context, $tags);
        self::checkReferences($context, $tags);
    }

    /**
     * Reports a `@see` tag with no reference on its line. A colon right after
     * `@see` isn't a reference.
     *
     * @param list<DocblockTag> $tags
     */
    public static function checkEmpty(LintContext $context, array $tags): void
    {
        foreach ($tags as $tag) {
            if ($tag->name !== 'see' || trim(self::reference($tag)->text) !== '') {
                continue;
            }

            $context->report(Issue::new('The @see tag must have content.', $tag->nameSpan));
        }
    }

    /**
     * Reports a colon right after `@see`, and a `@see` reference with other
     * text after it or with punctuation at its end. The fixes remove the
     * colon, and the punctuation after a one-word reference.
     *
     * @param list<DocblockTag> $tags
     */
    public static function checkReferences(LintContext $context, array $tags): void
    {
        // A `@see` after `@deprecated` holds the change-record url, whose
        // trailing periods `drupal/deprecated-tag` removes. A second edit
        // there would overlap that one.
        $deprecatedAt = null;
        foreach ($tags as $index => $tag) {
            if ($tag->name !== 'deprecated') {
                continue;
            }

            $deprecatedAt = $index;
            break;
        }

        foreach ($tags as $index => $tag) {
            if ($tag->name !== 'see') {
                continue;
            }

            $deprecated = $deprecatedAt !== null && $index > $deprecatedAt;

            // The reference is the tag's own line, as Coder reads it. The
            // lines below it are a description, which may end in a period.
            // A tag with its reference on the next line counts as empty.
            $line = self::reference($tag);
            self::checkColon($context, $tag, $line);

            $reference = rtrim($line->text);
            if (trim($reference) === '') {
                continue;
            }

            // Other text gets one issue, as in Coder. Punctuation at the end
            // is part of that text.
            [, $rest] = DocType::split(ltrim($reference));
            if ($rest !== '') {
                $context->report(Issue::new(
                    'The @see tag must have only a reference, with no other text.',
                    $tag->contentSpan(),
                ));

                continue;
            }

            $kept = rtrim($reference, characters: '.!?');
            if ($kept === $reference) {
                continue;
            }

            $issue = Issue::new('Do not end the @see reference with punctuation.', $tag->contentSpan());
            if (trim($kept) !== '' && !$deprecated) {
                $issue = $issue->withEdit(TextEdit::delete(
                    new Span($line->offset + strlen($kept), $line->offset + strlen($reference)),
                ));
            }

            $context->report($issue);
        }
    }

    /**
     * Reports a colon right after `@see`, as in `@see: url`. The fix turns
     * the colon and the spaces after it into one space, or removes them when
     * the line doesn't have a reference.
     */
    private static function checkColon(LintContext $context, DocblockTag $tag, DocblockLine $reference): void
    {
        if ($reference->offset === $tag->lines[0]->offset) {
            return;
        }

        $colon = $tag->nameSpan->end;
        $replacement = trim($reference->text) === '' ? '' : ' ';
        $context->report(Issue::new(
            'Remove the colon after @see.',
            new Span($colon, $colon + 1),
        )->withEdit(TextEdit::replace(new Span($colon, $reference->offset), $replacement)));
    }

    /**
     * Returns the tag's own line. A colon right after `@see` and the spaces
     * after it aren't part of it.
     */
    private static function reference(DocblockTag $tag): DocblockLine
    {
        $line = $tag->lines[0];
        if ($line->offset !== $tag->nameSpan->end || !str_starts_with($line->text, ':')) {
            return $line;
        }

        $skip = 1 + strspn($line->text, characters: " \t", offset: 1);

        return new DocblockLine(substr($line->text, $skip), $line->offset + $skip);
    }
}
