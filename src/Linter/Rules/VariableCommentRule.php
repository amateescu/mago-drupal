<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\DocblockTag;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\TriviaKind;

use function count;
use function in_array;
use function preg_match;
use function stripos;

/**
 * Checks that a class property has a `@var` docblock.
 *
 * Ports Drupal.Commenting.VariableComment. `IncorrectVarType`, which wants a
 * canonical scalar alias like `bool` instead of `Boolean`, is not ported: a
 * wrong-cased alias is not a real PHP type, so `mago analyze` already flags
 * it as unresolvable.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class VariableCommentRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/variable-comment',
            name: 'Variable comment',
            description: 'Checks that a class property has a @var docblock.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Property],
        );
    }

    public function lint(LintContext $context): void
    {
        $closest = Docblocks::closest($context->file, $context->node);

        if ($closest === null) {
            if (!$this->hasNativeType($context)) {
                $context->report(Issue::new('Missing property doc comment.', $context->node->span));
            }

            return;
        }

        if ($closest->kind !== TriviaKind::DocBlockComment) {
            $context->report(Issue::new('A property comment must use "/**" style comments.', $context->node->span));

            return;
        }

        // A property inheriting its parent's own docblock has nothing of
        // its own left to check here, the same exemption Coder's sniff
        // makes.
        if (stripos($context->file->getText($closest->span), needle: '{@inheritdoc}') !== false) {
            return;
        }

        $tags = Docblocks::tags($context->file, $closest->span);
        $this->checkTags($context, $tags);
    }

    /**
     * @param list<DocblockTag> $tags
     */
    private function checkTags(LintContext $context, array $tags): void
    {
        $varTags = [];
        foreach ($tags as $index => $tag) {
            if ($tag->name === 'var') {
                $varTags[] = [$index, $tag];
            }

            if ($tag->name === 'see' && $tag->content() === '') {
                $context->report(Issue::new('A @see tag must have content.', $tag->nameSpan));
            }
        }

        if ($varTags === []) {
            if (!$this->hasNativeType($context)) {
                $context->report(Issue::new('Missing @var tag in property doc comment.', $context->node->span));
            }

            return;
        }

        if (count($varTags) > 1) {
            $context->report(Issue::new('Only one @var tag is allowed for a property.', $varTags[1][1]->nameSpan));
        }

        [$firstIndex, $firstVar] = $varTags[0];
        if ($firstIndex !== 0) {
            $context->report(Issue::new(
                'The @var tag must be the first tag in a property doc comment.',
                $firstVar->nameSpan,
            ));
        }

        $content = $firstVar->content();
        if ($content === '') {
            $context->report(Issue::new('The @var tag must have a type.', $firstVar->nameSpan));

            return;
        }

        [$type, $rest] = Docblocks::splitType($content);
        if ($type !== null && preg_match('/^\$/', $rest) === 1) {
            $context->report(Issue::new(
                'A @var tag should not repeat the property name after its type.',
                $firstVar->contentSpan(),
            ));
        }
    }

    /**
     * Whether a property declaration already carries a native type hint,
     * which makes an explicit `@var` optional.
     *
     * Reads the parsed structure instead of the declaration's text: an
     * earlier, text-based version had to skip past modifier keywords by
     * hand, and an attribute above the property (`#[Attr]`) is more of that
     * same text to skip, one layer it did not account for. A native type
     * hint, when present, is a `Hint` node sitting directly under the
     * `PlainProperty`/`HookedProperty` node, alongside the modifiers and
     * attributes rather than nested inside them, which is what makes a
     * direct-children check sufficient here.
     */
    private function hasNativeType(LintContext $context): bool
    {
        foreach ($context->file->getChildren($context->node) as $child) {
            if (!in_array($child->kind, [NodeKind::PlainProperty, NodeKind::HookedProperty], strict: true)) {
                continue;
            }

            foreach ($context->file->getChildren($child) as $grandchild) {
                if ($grandchild->kind === NodeKind::Hint) {
                    return true;
                }
            }
        }

        return false;
    }
}
