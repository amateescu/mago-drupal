<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;

use function ltrim;
use function rtrim;
use function strlen;
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
 */
final class SeeTags
{
    private function __construct() {}

    /**
     * Reports a `@see` tag with no reference, then runs `checkReferences()`.
     *
     * @param list<DocblockTag> $tags
     */
    public static function check(LintContext $context, array $tags): void
    {
        foreach ($tags as $tag) {
            if ($tag->name !== 'see' || trim($tag->lines[0]->text) !== '') {
                continue;
            }

            $context->report(Issue::new('The @see tag must have content.', $tag->nameSpan));
        }

        self::checkReferences($context, $tags);
    }

    /**
     * Reports a `@see` reference with other text after it, or with
     * punctuation at its end. The fix removes the punctuation after a
     * one-word reference.
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
            $line = $tag->lines[0];
            $reference = rtrim($line->text);
            if (trim($reference) === '') {
                continue;
            }

            [, $rest] = Docblocks::splitType(ltrim($reference));
            if ($rest !== '') {
                $context->report(Issue::new(
                    'The @see tag must have only a reference, with no other text.',
                    $tag->contentSpan(),
                ));
            }

            $kept = rtrim($reference, characters: '.!?');
            if ($kept === $reference) {
                continue;
            }

            $issue = Issue::new('Do not end the @see reference with punctuation.', $tag->contentSpan());
            if ($rest === '' && trim($kept) !== '' && !$deprecated) {
                $issue = $issue->withEdit(TextEdit::delete(
                    new Span($line->offset + strlen($kept), $line->offset + strlen($reference)),
                ));
            }

            $context->report($issue);
        }
    }
}
