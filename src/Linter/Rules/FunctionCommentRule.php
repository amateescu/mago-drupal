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

use function array_filter;
use function array_values;
use function count;
use function in_array;
use function ltrim;
use function mb_strtoupper;
use function mb_substr;
use function preg_match;
use function rtrim;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;
use function trim;

/**
 * Checks that a function or method has a docblock, and that its `@param`,
 * `@return`, `@throws` and `@see` tags are structurally sound and well
 * worded.
 *
 * Ports Drupal.Commenting.FunctionComment. Most of what looks like the hard
 * part of this sniff, comparing a docblock's types against the real
 * signature, is not ported: `mago analyze` already reads `@param`/`@return`
 * as authoritative types when there is no native hint, so it already
 * reports a `void` return that returns a value, a function with no `return`
 * at all, an `@param` naming an unknown parameter, a bare tag with no type,
 * and most wrong-cased type aliases, which fail to resolve as a class. What
 * is left is presence, structure and prose. The one signature-dependent
 * check that survives is a method with partial `@param` coverage missing an
 * entry for a real parameter, which nothing else catches.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class FunctionCommentRule implements Rule
{
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
            $context->report(Issue::new('Missing function doc comment.', $context->node->span));

            return;
        }

        if ($closest->kind !== TriviaKind::DocBlockComment) {
            $context->report(Issue::new('A function comment must use "/**" style comments.', $context->node->span));

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
        return (
            $context->node->kind === NodeKind::Method
            && Nodes::declaredName($context->file, $context->node) === '__construct'
        );
    }

    /**
     * @param list<DocblockTag> $tags
     */
    private function checkParamTags(LintContext $context, array $tags): void
    {
        $paramTags = array_values(array_filter($tags, static fn(DocblockTag $tag): bool => $tag->name === 'param'));
        foreach ($paramTags as $tag) {
            $this->checkParamTag($context, $tag);
        }

        if ($context->node->kind === NodeKind::Method && $paramTags !== []) {
            $this->checkParamCoverage($context, $paramTags);
        }
    }

    private function checkParamTag(LintContext $context, DocblockTag $tag): void
    {
        $content = $tag->content();
        $matches = [];
        if (preg_match('/\$[A-Za-z_][A-Za-z0-9_]*/', $content, $matches) !== 1) {
            if (trim($content) !== '') {
                $context->report(Issue::new('The @param tag is missing a $variable name.', $tag->contentSpan()));
            }

            return;
        }

        $variable = $matches[0];
        $offset = strpos($content, $variable);
        $offset = $offset === false ? 0 : $offset;
        $type = trim(substr($content, offset: 0, length: $offset));
        $rest = substr($content, $offset + strlen($variable));

        if ($type === '') {
            $context->report(Issue::new('The @param tag is missing a type.', $tag->contentSpan()));
        }

        if (str_starts_with($rest, '.')) {
            $context->report(Issue::new(
                'The @param variable name must not be followed by a period.',
                $tag->contentSpan(),
            ));
        }

        $description = ltrim($rest, characters: ". \t");
        if ($description === '') {
            $context->report(Issue::new('The @param tag is missing a description.', $tag->contentSpan()));

            return;
        }

        $this->checkProse($context, $description, $tag->contentSpan(), 'param description');
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

            $context->report(Issue::new("Missing @param documentation for {$name}.", $context->node->span));
        }
    }

    /**
     * @return list<string>
     */
    private function realParameters(LintContext $context): array
    {
        $names = [];
        foreach ($context->file->getChildren($context->node) as $child) {
            if ($child->kind !== NodeKind::FunctionLikeParameterList) {
                continue;
            }

            foreach ($context->file->getChildren($child) as $parameter) {
                if ($parameter->kind !== NodeKind::FunctionLikeParameter) {
                    continue;
                }

                $variable = $context->file->getFirstDescendant($parameter, NodeKind::DirectVariable);
                if ($variable !== null) {
                    $names[] = $context->file->getText($variable);
                }
            }
        }

        return $names;
    }

    /**
     * @param list<DocblockTag> $tags
     */
    private function checkReturnTags(LintContext $context, array $tags): void
    {
        $returnTags = array_values(array_filter($tags, static fn(DocblockTag $tag): bool => $tag->name === 'return'));
        if (count($returnTags) > 1) {
            $context->report(Issue::new('Only one @return tag is allowed.', $returnTags[1]->nameSpan));
        }

        if ($returnTags === []) {
            return;
        }

        [$type, $rest] = Docblocks::splitType($returnTags[0]->content());
        if ($type === null) {
            // A bare @return with nothing after it is already reported by
            // mago analyze as a malformed docblock.
            return;
        }

        if (str_starts_with($rest, '$')) {
            $context->report(Issue::new(
                'The @return type should not be followed by a variable name.',
                $returnTags[0]->contentSpan(),
            ));

            return;
        }

        if ($rest === '' && !in_array($type, ['$this', 'static'], strict: true)) {
            $context->report(Issue::new('The @return tag is missing a description.', $returnTags[0]->contentSpan()));
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
                // An empty @throws is already reported by mago analyze as a
                // malformed docblock; a type-only @throws needs no description.
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
                    'The @see tag should contain only a reference, not additional text.',
                    $tag->contentSpan(),
                ));
            }

            $lastChar = mb_substr(rtrim($content), -1);
            if (in_array($lastChar, ['.', '!', '?'], strict: true)) {
                $context->report(Issue::new(
                    'The @see reference should not end with punctuation.',
                    $tag->contentSpan(),
                ));
            }
        }
    }

    /**
     * Checks that free-form text starts with a capital letter and ends with
     * terminal punctuation.
     */
    private function checkProse(LintContext $context, string $text, Span $span, string $label): void
    {
        $first = mb_substr($text, start: 0, length: 1);
        if ($first !== mb_strtoupper($first)) {
            $context->report(Issue::new("The {$label} must start with a capital letter.", $span));
        }

        $last = mb_substr(rtrim($text), -1);
        if (!in_array($last, ['.', '!', '?', ')'], strict: true)) {
            $context->report(Issue::new("The {$label} must end with terminal punctuation.", $span));
        }
    }
}
