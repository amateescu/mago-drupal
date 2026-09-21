<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\DocblockLine;
use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\DocblockTag;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\TriviaKind;

use function count;
use function explode;
use function in_array;
use function max;
use function mb_strtoupper;
use function mb_substr;
use function preg_match;
use function rtrim;
use function strlen;
use function strtolower;
use function trim;

/**
 * Checks a docblock's short description, long description and tag order.
 *
 * Ports the semantic half of Drupal.Commenting.DocComment. The rest of that
 * sniff is pure whitespace: star alignment, blank-line placement and
 * tag-value indentation. `mago format --preset drupal` already produces
 * that.
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
     * First-line markers of an api.module documentation group. This rule
     * skips such a group.
     *
     * A `@defgroup` or `@addtogroup` block is topic markup, not a
     * declaration's docblock. Its closing block is a bare `@}`. Coder skips
     * all of these on the first content token alone. A group block that
     * also holds `@section` or `@see` markup is thus still exempt. A check
     * that every tag is exempt reports real api.php group blocks.
     */
    private const GROUP_MARKERS = ['@defgroup', '@addtogroup', '@coversdefaultclass', '@}'];

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
        // Function and Method are targets. Rust then collects every one of
        // them into the file's target-node list, and the Program pass below
        // reads that list at no cost. Their own dispatches do nothing. A
        // getNodes() call per kind takes about 0.13ms per file per kind on
        // Drupal core, because each call is a full, unindexed re-scan.
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

        // A docblock inside a function-like body belongs to InlineComment,
        // not to this rule. A local `/** @var Foo $x */` annotation is a
        // common idiom, not a malformed declaration comment. The rule does
        // not skip a `Closure`. Coder's own sniff does not skip a top-level
        // closure either, because it tests only `T_FUNCTION`. A nested
        // closure is inside a covered body in any case. The rule does not
        // cover a PHP 8.4 property hook body. Drupal 11 runs on PHP 8.3.
        //
        // The target list is in source order. The bodies are thus kept as
        // two parallel arrays: each start, and the furthest end seen up to
        // it. A docblock then binary-searches the last body that starts
        // before it. The docblock is inside a body if that furthest end is
        // past the docblock. A scan of every body for every docblock is
        // quadratic on a class with hundreds of methods.
        $starts = [];
        $ends = [];
        $furthest = 0;
        foreach ($context->file->getTargetNodes() as $node) {
            if ($node->kind !== NodeKind::Function && $node->kind !== NodeKind::Method) {
                continue;
            }

            $furthest = max($furthest, $node->span->end);
            $starts[] = $node->span->start;
            $ends[] = $furthest;
        }

        foreach ($context->file->getTrivia() as $trivia) {
            if ($trivia->kind !== TriviaKind::DocBlockComment) {
                continue;
            }

            if ($this->isInsideAFunctionLikeBody($trivia->span, $starts, $ends)) {
                continue;
            }

            $this->checkDocblock($context, $trivia->span);
        }
    }

    /**
     * @param list<int> $starts Body starts, ascending.
     * @param list<int> $ends The furthest body end up to each start.
     */
    private function isInsideAFunctionLikeBody(Span $span, array $starts, array $ends): bool
    {
        $low = 0;
        $high = count($starts) - 1;
        $index = null;
        while ($low <= $high) {
            $middle = ($low + $high) >> 1;
            if ($starts[$middle] > $span->start) {
                $high = $middle - 1;

                continue;
            }

            $index = $middle;
            $low = $middle + 1;
        }

        return $index !== null && $ends[$index] >= $span->end;
    }

    private function checkDocblock(LintContext $context, Span $span): void
    {
        if ($this->isDocumentationGroup($context, $span)) {
            return;
        }

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

            $context->report(Issue::new(
                'Write @inheritdoc as {@inheritdoc}, with curly braces, to make it an inline tag.',
                $tag->nameSpan,
            ));
        }

        if ($summary === [] && !$this->exemptFromShortDescription($tags)) {
            $context->report(Issue::new('The docblock has no short description.', $span));
        }

        if ($summary !== []) {
            $this->checkParagraph($context, $summary, 'short description', strictPunctuation: true);
            if (count($summary) > 1) {
                $context->report(Issue::new(
                    'A short description must fit on one line. Move the rest to a long description.',
                    $this->paragraphSpan($summary),
                ));
            }
        }

        if ($description !== []) {
            $this->checkParagraph($context, $description, 'long description', strictPunctuation: false);
        }

        $this->checkTagOrder($context, $tags);
    }

    /**
     * Whether a docblock's first content marks it as a documentation group.
     */
    private function isDocumentationGroup(LintContext $context, Span $span): bool
    {
        foreach (Docblocks::lines($context->file, $span) as $line) {
            $text = trim($line->text);
            if ($text === '') {
                continue;
            }

            $firstWord = strtolower(explode(' ', $text, limit: 2)[0]);

            return in_array($firstWord, self::GROUP_MARKERS, strict: true);
        }

        return false;
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
     * Checks that a paragraph's first line starts with a capital letter and
     * its last line ends with terminal punctuation.
     *
     * $strictPunctuation follows the split in Coder's own sniff. A short
     * description must end in one of a fixed set of terminal marks. A long
     * description is only reported when it ends with a bare letter. A long
     * description can end with a colon before a list, a quoted token, or a
     * digit. A report on those gives dozens of false positives on real
     * Drupal core docblocks. The flag stays a flag because both callers
     * share everything else in here, and each call site names it.
     *
     * @param list<DocblockLine> $paragraph
     *
     * @mago-expect lint:no-boolean-flag-parameter
     */
    private function checkParagraph(
        LintContext $context,
        array $paragraph,
        string $label,
        bool $strictPunctuation,
    ): void {
        $first = $paragraph[0]->text;
        $firstChar = mb_substr($first, start: 0, length: 1);
        if (rtrim($first) !== '{@inheritdoc}' && $firstChar !== mb_strtoupper($firstChar)) {
            // The span uses the byte length of $firstChar, not a hardcoded 1.
            // With a multi-byte character such as "É" or "€", a 1 puts the
            // span's end in the middle of the character.
            $context->report(Issue::new(
                "The {$label} must start with a capital letter.",
                new Span($paragraph[0]->offset, $paragraph[0]->offset + strlen($firstChar)),
            ));
        }

        $last = $paragraph[count($paragraph) - 1];
        $trimmed = rtrim($last->text);
        $lastChar = mb_substr($trimmed, -1);
        // Both comparisons use the trimmed text. Otherwise a stray trailing
        // space, or a "\r" that the line splitter keeps under CRLF, makes
        // the text look unexempt. The last character is judged after the
        // trim. The exemption for the exact text then fails.
        $unpunctuated = $strictPunctuation
            ? !in_array($lastChar, ['.', '!', '?', ')'], strict: true)
            : preg_match('/[a-zA-Z]/', $lastChar) === 1;
        if ($trimmed !== '{@inheritdoc}' && $unpunctuated) {
            $lastCharEnd = $last->offset + strlen($trimmed);
            $context->report(Issue::new(
                "The {$label} must end with terminal punctuation.",
                new Span($lastCharEnd - strlen($lastChar), $lastCharEnd),
            ));
        }
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
