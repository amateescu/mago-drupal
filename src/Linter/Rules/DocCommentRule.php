<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\DocblockLine;
use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\DocblockTag;
use amateescu\MagoDrupal\Internal\DocCommentSpacing;
use amateescu\MagoDrupal\Internal\OuterDocblocks;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;

use function count;
use function in_array;
use function mb_strtoupper;
use function mb_substr;
use function rtrim;
use function strlen;
use function strtolower;

/**
 * Checks a docblock's short description, long description and tag order.
 *
 * Ports Drupal.Commenting.DocComment. `DocCommentSpacing` holds the checks
 * on blank lines and on the spaces before a description and after a tag.
 * Star alignment is left to `mago format`. The end of the long description
 * is `drupal/long-description-punctuation`'s, since core's `phpcs.xml.dist`
 * turns that check off. A `phpcs:` line inside the docblock is not part of
 * a description, as Coder reads it.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class DocCommentRule implements Rule
{
    /**
     * Tags that can make up a docblock on their own, with no short
     * description.
     *
     * A file comment writes its description as continuation lines of the
     * `@file` tag, not as a leading paragraph. That is why `file` is in this
     * list. The exemption only applies to a docblock whose only tag is
     * `@file`. A docblock that mixes `@file` with other tags must still have
     * a leading short description. `var` is not in the list, although a
     * `@var`-only property docblock is common. Coder's own sniff does not
     * exempt it either, and reports `MissingShort` there.
     */
    private const EXEMPT_ONLY_TAGS = ['covers', 'coversdefaultclass', 'file'];

    /**
     * Tags that this rule checks for order. Each must also be in one group.
     */
    private const ORDERED_TAGS = ['param', 'return', 'throws'];

    /**
     * Leading tags that do not count as a tag group. A `@param` group can
     * follow them and still count as first, because they are markup.
     */
    private const PARAM_LEADING_EXEMPT = ['code', 'todo', 'link', 'endlink', 'codingstandardsignorestart'];

    public function getDefinition(): RuleDefinition
    {
        // Function and Method are targets for `OuterDocblocks::of()`. Their
        // own dispatches do nothing.
        return new RuleDefinition(
            code: 'drupal/doc-comment',
            name: 'Doc comment',
            description: "Checks a docblock's short description, long description and tag order.",
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program, NodeKind::Function, NodeKind::Method],
        );
    }

    public function lint(LintContext $context): void
    {
        if ($context->node->kind !== NodeKind::Program) {
            return;
        }

        foreach (OuterDocblocks::of($context->file) as $span) {
            $this->checkDocblock($context, $span);
        }
    }

    private function checkDocblock(LintContext $context, Span $span): void
    {
        DocCommentSpacing::checkEnds($context, $span);
        if (OuterDocblocks::isGroup($context->file, $span)) {
            return;
        }

        DocCommentSpacing::checkBody($context, $span);

        $tags = Docblocks::tags($context->file, $span);
        [$summary, $description] = Docblocks::paragraphs($context->file, $span);

        if ($summary === [] && $tags === []) {
            $context->report(Issue::new('The docblock is empty.', $span));

            return;
        }

        foreach ($tags as $tag) {
            if ($tag->name !== 'inheritdoc') {
                continue;
            }

            // A stray closing brace, as in `@inheritdoc}`, goes into the
            // replacement too, so the result does not end in two of them.
            $replaced = $tag->nameSpan;
            if (($context->file->contents[$replaced->end] ?? '') === '}') {
                $replaced = new Span($replaced->start, $replaced->end + 1);
            }

            $context->report(Issue::new(
                'Write @inheritdoc as {@inheritdoc}, with curly braces, to make it an inline tag.',
                $tag->nameSpan,
            )->withEdit(TextEdit::replace($replaced, '{@inheritdoc}')));
        }

        if ($summary === [] && !$this->exemptFromShortDescription($tags)) {
            $context->report(Issue::new('The docblock has no short description.', $span));
        }

        if ($summary !== []) {
            $this->checkCapital($context, $summary, 'short description');
            $this->checkSummaryEnd($context, $summary);
            if (count($summary) > 1) {
                $context->report(Issue::new(
                    'A short description must fit on one line. Move the rest to a long description.',
                    $this->paragraphSpan($summary),
                ));
            }
        }

        if ($description !== []) {
            $this->checkCapital($context, $description, 'long description');
        }

        $this->checkTagOrder($context, $tags);
    }

    /**
     * Whether a docblock can have no short description, because it only has
     * tags that stand on their own.
     *
     * @param list<DocblockTag> $tags
     */
    private function exemptFromShortDescription(array $tags): bool
    {
        if ($tags === []) {
            return false;
        }

        foreach ($tags as $tag) {
            if (!in_array($tag->name, self::EXEMPT_ONLY_TAGS, strict: true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks that a paragraph's first line starts with a capital letter.
     *
     * @param list<DocblockLine> $paragraph
     */
    private function checkCapital(LintContext $context, array $paragraph, string $label): void
    {
        $first = $paragraph[0]->text;
        $firstChar = mb_substr($first, start: 0, length: 1);
        if (self::isInheritdoc($first) || $firstChar === mb_strtoupper($firstChar)) {
            return;
        }

        // The span uses the byte length of $firstChar, not a hardcoded 1.
        // With a multi-byte character such as "É" or "€", a 1 puts the span's
        // end in the middle of the character.
        $context->report(Issue::new(
            "The {$label} must start with a capital letter.",
            new Span($paragraph[0]->offset, $paragraph[0]->offset + strlen($firstChar)),
        ));
    }

    /**
     * Checks that the short description ends in one of a fixed set of
     * terminal marks. `drupal/long-description-punctuation` checks the long
     * description, more loosely.
     *
     * @param list<DocblockLine> $summary
     */
    private function checkSummaryEnd(LintContext $context, array $summary): void
    {
        $last = $summary[count($summary) - 1];
        // The text is trimmed first. Otherwise a stray trailing space, or a
        // "\r" that the line splitter keeps under CRLF, makes it look
        // unexempt.
        $trimmed = rtrim($last->text);
        $lastChar = mb_substr($trimmed, -1);
        if (self::isInheritdoc($trimmed) || in_array($lastChar, ['.', '!', '?', ')'], strict: true)) {
            return;
        }

        $lastCharEnd = $last->offset + strlen($trimmed);
        $context->report(Issue::new(
            'The short description must end with terminal punctuation.',
            new Span($lastCharEnd - strlen($lastChar), $lastCharEnd),
        ));
    }

    /**
     * Whether the text is the inline tag alone, in any case. Coder accepts
     * `{@inheritDoc}` too.
     */
    private static function isInheritdoc(string $text): bool
    {
        return strtolower(rtrim($text)) === '{@inheritdoc}';
    }

    /**
     * @param list<DocblockLine> $paragraph
     */
    private function paragraphSpan(array $paragraph): Span
    {
        $last = $paragraph[count($paragraph) - 1];

        return new Span($paragraph[0]->offset, $last->offset + strlen($last->text));
    }

    /**
     * Reports a tag name that appears again after another tag interrupted
     * it, and an `@param` group that is not the first group of tags.
     *
     * Only `@param`, `@return` and `@throws` are ordered tags. No other tag
     * counts here, and that includes Doxygen markup such as `@code`,
     * `@endcode`, `@todo` and `@link`. Such a tag does not have to be in a
     * group. It also does not make a later `@param` group count as not
     * first. A docblock often opens with an example before its first real
     * tag.
     *
     * @param list<DocblockTag> $tags
     */
    private function checkTagOrder(LintContext $context, array $tags): void
    {
        $firstName = $tags === [] ? null : $tags[0]->name;
        $paramMayFollow = in_array($firstName, self::PARAM_LEADING_EXEMPT, strict: true);

        $seen = [];
        // Tracks the name of the tag just before, ordered or not. Otherwise
        // the group check does not see an "@code" between two "@param"
        // groups.
        $lastName = null;
        foreach ($tags as $index => $tag) {
            if (in_array($tag->name, self::ORDERED_TAGS, strict: true)) {
                $alreadySeen = $seen[$tag->name] ?? false;

                if ($tag->name === 'param' && $index > 0 && !$alreadySeen && !$paramMayFollow) {
                    $context->report(Issue::new(
                        '@param tags must be the first group of tags in a docblock.',
                        $tag->nameSpan,
                    ));
                }

                if ($alreadySeen && $lastName !== $tag->name) {
                    $context->report(Issue::new(
                        "Keep the @{$tag->name} tags together. Do not put other tags between them.",
                        $tag->nameSpan,
                    ));
                }

                $seen[$tag->name] = true;
            }

            $lastName = $tag->name;
        }
    }
}
