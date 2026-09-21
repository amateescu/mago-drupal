<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\DocblockTag;
use amateescu\MagoDrupal\Internal\Nodes;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\TriviaKind;

use function array_reverse;
use function array_values;
use function count;
use function in_array;
use function ltrim;
use function mb_strtoupper;
use function mb_substr;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function rtrim;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;
use function trim;

/**
 * Checks that a function or method has a docblock, and that its `@param`,
 * `@return`, `@throws` and `@see` tags have the correct structure and
 * wording.
 *
 * Ports Drupal.Commenting.FunctionComment. The comparison of a docblock's
 * types against the real signature is the difficult part of this sniff,
 * and most of it is not ported. `mago analyze` already reads `@param` and
 * `@return` as authoritative types when there is no native hint. It thus
 * already reports a `void` return that returns a value, a function with no
 * `return` at all, an `@param` that names an unknown parameter, and a bare
 * tag with no type. It also reports most wrong-cased type aliases, because
 * they do not resolve as a class. What is left is presence, structure and
 * prose. One signature-dependent check stays: a method with partial
 * `@param` coverage that has no entry for a real parameter. Nothing else
 * reports that.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class FunctionCommentRule implements Rule
{
    /**
     * A quoted string, a block comment or a line comment. `#[` opens an
     * attribute, not a comment.
     */
    private const QUOTED_OR_COMMENT = '/\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"|\/\*.*?\*\/|(?:\/\/|#(?!\[))[^\n]*/s';

    private string $mentionPath = '';

    /**
     * @var list<int>
     */
    private array $constructorMentions = [];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/function-comment',
            name: 'Function comment',
            description: 'Checks that a function or method has a well-formed docblock.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Function, NodeKind::Method],
        );
    }

    public function lint(LintContext $context): void
    {
        if ($this->isConstructor($context)) {
            return;
        }

        $closest = Docblocks::closest($context->file, $context->node);
        if ($closest === null) {
            $context->report(Issue::new('The function has no docblock.', $context->node->span));

            return;
        }

        if ($closest->kind !== TriviaKind::DocBlockComment) {
            $context->report(Issue::new('The function docblock must start with "/**".', $context->node->span));

            return;
        }

        $tags = Docblocks::tags($context->file, $closest->span);
        $this->checkParamTags($context, $tags);
        $this->checkReturnTags($context, $tags);
        $this->checkThrowsTags($context, $tags);
        $this->checkSeeTags($context, $tags);
    }

    private function isConstructor(LintContext $context): bool
    {
        if ($context->node->kind !== NodeKind::Method) {
            return false;
        }

        // A method whose text does not have the name cannot be the
        // constructor. The rule finds the file's mentions once. Checking a
        // method against them excludes almost every method without reading
        // its nodes. The name lookup below must read the nodes.
        if ($this->mentionPath !== $context->file->path) {
            $this->mentionPath = $context->file->path;
            $this->constructorMentions = self::mentionOffsets($context->file->contents);
        }

        $span = $context->node->span;
        foreach ($this->constructorMentions as $offset) {
            if ($offset >= $span->start && $offset < $span->end) {
                return Nodes::declaredName($context->file, $context->node) === '__construct';
            }
        }

        return false;
    }

    /**
     * The byte offsets of every `__construct` in the file.
     *
     * @return list<int>
     */
    private static function mentionOffsets(string $contents): array
    {
        $offsets = [];
        $offset = strpos($contents, needle: '__construct');
        while ($offset !== false) {
            $offsets[] = $offset;
            $offset = strpos($contents, needle: '__construct', offset: $offset + 1);
        }

        return $offsets;
    }

    /**
     * @param list<DocblockTag> $tags
     */
    private function checkParamTags(LintContext $context, array $tags): void
    {
        $paramTags = [];
        foreach ($tags as $tag) {
            if ($tag->name !== 'param') {
                continue;
            }

            $paramTags[] = $tag;
            $this->checkParamTag($context, $tag, $tags);
        }

        if ($context->node->kind === NodeKind::Method && $paramTags !== []) {
            $this->checkParamCoverage($context, $paramTags);
        }
    }

    /**
     * Whether an example follows a `@param` description.
     *
     * Drupal writes the example inside the description. It is either
     * indented with the text, or at the star column, where it parses as a
     * tag of its own. Coder skips the same markers when it reads a param
     * comment. With both spellings, the check for terminal punctuation thus
     * applies to the description, not to the example's last line. Coder
     * skips `@link` the same way, but a link is not an example. The
     * description before it is prose and keeps its check.
     *
     * @param list<DocblockTag> $tags
     */
    private function precedesExample(array $tags, DocblockTag $tag): bool
    {
        if ($this->endsInExample($tag)) {
            return true;
        }

        $index = Docblocks::indexOf($tags, $tag);
        if ($index === null) {
            return false;
        }

        $next = $tags[$index + 1] ?? null;

        return $next !== null && in_array($next->name, Docblocks::EXAMPLE_TAGS, strict: true);
    }

    /**
     * @param list<DocblockTag> $tags Every tag in the docblock. The rule
     *   uses them to find whether an example follows the description.
     */
    private function checkParamTag(LintContext $context, DocblockTag $tag, array $tags): void
    {
        $content = $tag->content();
        $matches = [];
        if (preg_match('/\$[A-Za-z_][A-Za-z0-9_]*/', $content, $matches) !== 1) {
            if (trim($content) !== '') {
                $context->report(Issue::new('The @param tag has no $variable name.', $tag->contentSpan()));
            }

            return;
        }

        $variable = $matches[0];
        $offset = strpos($content, $variable);
        $offset = $offset === false ? 0 : $offset;
        $type = trim(substr($content, offset: 0, length: $offset));
        $rest = substr($content, $offset + strlen($variable));

        if ($type === '') {
            $context->report(Issue::new('The @param tag has no type.', $tag->contentSpan()));
        }

        if (str_starts_with($rest, '.')) {
            $context->report(Issue::new('Do not put a period after the @param variable name.', $tag->contentSpan()));
        }

        $description = ltrim($rest, characters: ". \t");
        if ($description === '') {
            $context->report(Issue::new('The @param tag has no description.', $tag->contentSpan()));

            return;
        }

        $this->checkProseStart($context, $description, $tag->contentSpan(), 'param description');

        // A description with an example after it ends on the example, not on
        // a sentence. The ported sniff exempts that.
        if (!$this->precedesExample($tags, $tag)) {
            $this->checkProseEnd($context, $description, $tag->contentSpan(), 'param description');
        }
    }

    /**
     * @param list<DocblockTag> $paramTags
     */
    private function checkParamCoverage(LintContext $context, array $paramTags): void
    {
        $documented = [];
        foreach ($paramTags as $tag) {
            $matches = [];
            if (preg_match('/\$[A-Za-z_][A-Za-z0-9_]*/', $tag->content(), $matches) === 1) {
                $documented[$matches[0]] = true;
            }
        }

        foreach ($this->realParameters($context) as $name) {
            if ($documented[$name] ?? false) {
                continue;
            }

            $context->report(Issue::new("The docblock has no @param tag for {$name}.", $context->node->span));
        }
    }

    /**
     * @return list<string>
     */
    private function realParameters(LintContext $context): array
    {
        foreach ($context->file->getChildren($context->node) as $child) {
            if ($child->kind !== NodeKind::FunctionLikeParameterList) {
                continue;
            }

            // The rule reads the variables off the list's text, not its nodes.
            // The nodes take several reads per parameter. A type, an
            // attribute or a default value holds no variable. The only other
            // places for a `$` are a quoted string or a comment. The rule
            // blanks those first.
            $text =
                preg_replace(self::QUOTED_OR_COMMENT, replacement: '', subject: $context->file->getText($child)) ?? '';
            $matches = [];
            preg_match_all('/\$[A-Za-z_][A-Za-z0-9_]*/', $text, $matches);

            return array_values($matches[0]);
        }

        return [];
    }

    /**
     * @param list<DocblockTag> $tags
     */
    private function checkReturnTags(LintContext $context, array $tags): void
    {
        $returnTags = [];
        foreach ($tags as $tag) {
            if ($tag->name !== 'return') {
                continue;
            }

            $returnTags[] = $tag;
        }

        if (count($returnTags) > 1) {
            $context->report(Issue::new('Use only one @return tag.', $returnTags[1]->nameSpan));
        }

        if ($returnTags === []) {
            return;
        }

        [$type, $rest] = Docblocks::splitType($returnTags[0]->content());
        if ($type === null) {
            // mago analyze already reports a bare @return with nothing after
            // it as a malformed docblock.
            return;
        }

        if (str_starts_with($rest, '$')) {
            $context->report(Issue::new(
                'Do not put a variable name after the @return type.',
                $returnTags[0]->contentSpan(),
            ));

            return;
        }

        if ($rest === '' && !in_array($type, ['$this', 'static'], strict: true)) {
            $context->report(Issue::new('The @return tag has no description.', $returnTags[0]->contentSpan()));
        }
    }

    /**
     * @param list<DocblockTag> $tags
     */
    private function checkThrowsTags(LintContext $context, array $tags): void
    {
        foreach ($tags as $tag) {
            if ($tag->name !== 'throws') {
                continue;
            }

            [$type, $rest] = Docblocks::splitType($tag->content());
            if ($type === null || $rest === '') {
                // mago analyze already reports an empty @throws as a malformed
                // docblock. A description is not necessary for a type-only
                // @throws.
                continue;
            }

            $this->checkProse($context, $rest, $tag->contentSpan(), '@throws description');
        }
    }

    /**
     * @param list<DocblockTag> $tags
     */
    private function checkSeeTags(LintContext $context, array $tags): void
    {
        foreach ($tags as $tag) {
            if ($tag->name !== 'see') {
                continue;
            }

            $content = $tag->content();
            if ($content === '') {
                $context->report(Issue::new('The @see tag must have content.', $tag->nameSpan));

                continue;
            }

            [, $rest] = Docblocks::splitType($content);
            if ($rest !== '') {
                $context->report(Issue::new(
                    'The @see tag must have only a reference, with no other text.',
                    $tag->contentSpan(),
                ));
            }

            $lastChar = mb_substr(rtrim($content), -1);
            if (in_array($lastChar, ['.', '!', '?'], strict: true)) {
                $context->report(Issue::new('Do not end the @see reference with punctuation.', $tag->contentSpan()));
            }
        }
    }

    /**
     * Whether a tag's last line is a `@code` example, not prose.
     */
    private function endsInExample(DocblockTag $tag): bool
    {
        foreach (array_reverse($tag->lines) as $line) {
            $text = trim($line->text);
            if ($text === '') {
                continue;
            }

            return str_starts_with($text, '@code') || str_starts_with($text, '@endcode');
        }

        return false;
    }

    /**
     * Checks that free-form text starts with a capital letter and ends with
     * terminal punctuation.
     */
    private function checkProse(LintContext $context, string $text, Span $span, string $label): void
    {
        $this->checkProseStart($context, $text, $span, $label);
        $this->checkProseEnd($context, $text, $span, $label);
    }

    /**
     * Checks that free-form text starts with a capital letter.
     */
    private function checkProseStart(LintContext $context, string $text, Span $span, string $label): void
    {
        $first = mb_substr($text, start: 0, length: 1);
        if ($first !== mb_strtoupper($first)) {
            $context->report(Issue::new("The {$label} must start with a capital letter.", $span));
        }
    }

    /**
     * Checks that free-form text ends with terminal punctuation.
     */
    private function checkProseEnd(LintContext $context, string $text, Span $span, string $label): void
    {
        $last = mb_substr(rtrim($text), -1);
        if (!in_array($last, ['.', '!', '?', ')'], strict: true)) {
            $context->report(Issue::new("The {$label} must end with terminal punctuation.", $span));
        }
    }
}
