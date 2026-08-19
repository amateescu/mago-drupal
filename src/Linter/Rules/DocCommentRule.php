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
 * sniff is pure whitespace (star alignment, blank-line placement, tag-value
 * indentation), already produced by `mago format --preset drupal`.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class DocCommentRule implements Rule
{
    /**
     * Tags a docblock may consist of on its own, with no short description
     * required.
     *
     * A file comment writes its description as `@file`'s own continuation
     * lines rather than as a leading paragraph, which is why `file` belongs
     * here too. This only recognizes a docblock whose sole tag is `@file`;
     * one that mixes `@file` with something else still expects a leading
     * short description. `var` is deliberately absent even though a
     * `@var`-only property docblock is common: Coder's own sniff does not
     * exempt it either, and does report `MissingShort` there.
     */
    private const EXEMPT_ONLY_TAGS = ['covers', 'coversdefaultclass', 'file'];

    /**
     * First-line markers of an api.module documentation group, which this
     * rule skips entirely.
     *
     * A `@defgroup`/`@addtogroup` block is topic markup, not a declaration's
     * docblock, and its closing companion is a bare `@}`. Coder skips all of
     * these on the first content token alone, so a group block that goes on
     * to hold `@section`/`@see` markup is still exempt; requiring every tag
     * to be exempt instead flagged real api.php group blocks.
     */
    private const GROUP_MARKERS = ['@defgroup', '@addtogroup', '@coversdefaultclass', '@}'];

    /**
     * Tags whose grouping and order this rule checks.
     */
    private const ORDERED_TAGS = ['param', 'return', 'throws'];

    /**
     * A leading tag that lets a `@param` group start later without being
     * "not first", because it is markup rather than a competing tag group.
     */
    private const PARAM_LEADING_EXEMPT = ['code', 'todo', 'link', 'endlink', 'codingstandardsignorestart'];

    public function getDefinition(): RuleDefinition
    {
        // Function and Method are declared as targets so Rust collects
        // every one of them into the file's pre-computed target-node list,
        // which the Program pass below reads for free. Their own dispatches
        // are no-ops. Asking the node table instead (getNodes per kind) was
        // measured at roughly 0.13ms per file per kind on Drupal core: each
        // call is a full, unindexed re-scan.
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

        // A docblock inside a function-like body is InlineComment's
        // territory, not this rule's: a `/** @var Foo $x */`-style local
        // annotation is a real, common idiom, not a malformed declaration
        // comment. `Closure` is deliberately not skipped: a closure at the
        // top level is not skipped by Coder's own sniff either (it tests
        // only `T_FUNCTION`), and a nested one sits inside a covered body
        // anyway. A PHP 8.4 property hook body is a known gap, beyond even
        // Drupal 11's PHP floor; revisit when the ecosystem gets there.
        $functionLikeSpans = [];
        foreach ($context->file->getTargetNodes() as $node) {
            if ($node->kind !== NodeKind::Function && $node->kind !== NodeKind::Method) {
                continue;
            }

            $functionLikeSpans[] = $node->span;
        }

        foreach ($context->file->getTrivia() as $trivia) {
            if ($trivia->kind !== TriviaKind::DocBlockComment) {
                continue;
            }

            if ($this->isInsideAFunctionLikeBody($trivia->span, $functionLikeSpans)) {
                continue;
            }

            $this->checkDocblock($context, $trivia->span);
        }
    }

    /**
     * @param list<Span> $functionLikeSpans
     */
    private function isInsideAFunctionLikeBody(Span $span, array $functionLikeSpans): bool
    {
        foreach ($functionLikeSpans as $functionLikeSpan) {
            if ($functionLikeSpan->contains($span)) {
                return true;
            }
        }

        return false;
    }

    private function checkDocblock(LintContext $context, Span $span): void
    {
        if ($this->isDocumentationGroup($context, $span)) {
            return;
        }

        $tags = Docblocks::tags($context->file, $span);
        [$summary, $description] = Docblocks::paragraphs($context->file, $span);

        if ($summary === [] && $tags === []) {
            $context->report(Issue::new('Doc comment is empty.', $span));

            return;
        }

        foreach ($tags as $tag) {
            if ($tag->name !== 'inheritdoc') {
                continue;
            }

            $context->report(Issue::new(
                '{@inheritdoc} must be wrapped in curly braces to be recognized as an inline tag.',
                $tag->nameSpan,
            ));
        }

        if ($summary === [] && !$this->exemptFromShortDescription($tags)) {
            $context->report(Issue::new('Doc comment is missing a short description.', $span));
        }

        if ($summary !== []) {
            $this->checkParagraph($context, $summary, 'short description', strictPunctuation: true);
            if (count($summary) > 1) {
                $context->report(Issue::new(
                    'A short description must fit on a single line; move the rest to a long description.',
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
     * Whether a docblock without a short description is allowed to skip one,
     * because it consists only of tags that stand on their own.
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
     * $strictPunctuation follows the split in Coder's own sniff: a short
     * description must end in one of a fixed set of terminal marks, but a
     * long description only gets reported when it plainly trails off with a
     * bare letter. A long description legitimately ends with a colon
     * introducing a list, a quoted token, or a digit, and flagging those
     * produced dozens of false positives on real Drupal core docblocks.
     * The flag stays a flag because both callers share everything else in
     * here, and each call site names it.
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
            // $firstChar's own byte length, not a hardcoded 1: a multi-byte
            // character (e.g. "É" or "€") would otherwise put the span's end
            // mid-character.
            $context->report(Issue::new(
                "The {$label} must start with a capital letter.",
                new Span($paragraph[0]->offset, $paragraph[0]->offset + strlen($firstChar)),
            ));
        }

        $last = $paragraph[count($paragraph) - 1];
        $trimmed = rtrim($last->text);
        $lastChar = mb_substr($trimmed, -1);
        // Compares the trimmed text on both sides. A stray trailing space
        // (or a "\r" the line-splitter left in under CRLF) would otherwise
        // read as unexempt while its own last character is judged after
        // trimming, defeating the exemption for the exact text it names.
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
     * Reports a tag name that reappears after another tag interrupted it,
     * and an `@param` group that is not the first group of tags.
     *
     * Only `@param`, `@return` and `@throws` are ordered tags. Everything
     * else, including Doxygen markup like `@code`/`@endcode`/`@todo`/`@link`,
     * is transparent here: it neither has to be grouped itself nor counts
     * against `@param` needing to come first, matching how a docblock
     * commonly opens with an example before its first real tag.
     *
     * @param list<DocblockTag> $tags
     */
    private function checkTagOrder(LintContext $context, array $tags): void
    {
        $firstName = $tags === [] ? null : $tags[0]->name;
        $paramMayFollow = in_array($firstName, self::PARAM_LEADING_EXEMPT, strict: true);

        $seen = [];
        // Tracks the immediately preceding tag's name regardless of whether
        // that tag is itself ordered, or grouping never notices an
        // in-between "@code" breaking up two "@param" groups.
        $lastName = null;
        foreach ($tags as $index => $tag) {
            if (in_array($tag->name, self::ORDERED_TAGS, strict: true)) {
                $alreadySeen = $seen[$tag->name] ?? false;

                if ($tag->name === 'param' && $index > 0 && !$alreadySeen && !$paramMayFollow) {
                    $context->report(Issue::new(
                        '@param tags must be the first group of tags in a doc comment.',
                        $tag->nameSpan,
                    ));
                }

                if ($alreadySeen && $lastName !== $tag->name) {
                    $context->report(Issue::new(
                        "@{$tag->name} tags must be grouped together, not split apart by other tags.",
                        $tag->nameSpan,
                    ));
                }

                $seen[$tag->name] = true;
            }

            $lastName = $tag->name;
        }
    }
}
