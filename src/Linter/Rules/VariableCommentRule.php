<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\CommentDocblock;
use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\DocblockTag;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\TriviaKind;

use function array_values;
use function count;
use function in_array;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_replace;
use function stripos;
use function strlen;
use function strspn;
use function substr;

/**
 * Checks that a class property has a `@var` docblock.
 *
 * Ports Drupal.Commenting.VariableComment. `IncorrectVarType` wants a
 * canonical scalar alias such as `bool` instead of `Boolean`. The rule does
 * not port it. A wrong-cased alias is not a real PHP type, so `mago analyze`
 * already reports it as unresolvable.
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

        // Coder wants a docblock on a typed property too. The type only makes
        // its @var tag optional.
        if ($closest === null) {
            $context->report(Issue::new('The property has no docblock.', $context->node->span));

            return;
        }

        if ($closest->kind !== TriviaKind::DocBlockComment) {
            $issue = Issue::new('The property docblock must start with "/**".', $context->node->span);
            $fix = CommentDocblock::edit($context->file, $closest, $context->node->span->start);
            $context->report($fix === null ? $issue : $issue->withEdit($fix));

            return;
        }

        // A property that inherits its parent's docblock has nothing of its
        // own to check here. Coder's sniff makes the same exemption.
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
                $context->report(Issue::new('The @see tag must have content.', $tag->nameSpan));
            }
        }

        if ($varTags === []) {
            if (!$this->hasNativeType($context)) {
                $context->report(Issue::new('The property docblock has no @var tag.', $context->node->span));
            }

            return;
        }

        if (count($varTags) > 1) {
            $context->report(Issue::new('Use only one @var tag for a property.', $varTags[1][1]->nameSpan));
        }

        [$firstIndex, $firstVar] = $varTags[0];
        if ($firstIndex !== 0) {
            $context->report(Issue::new('Put the @var tag first in the property docblock.', $firstVar->nameSpan));
        }

        $content = $firstVar->content();
        if ($content === '') {
            $context->report(Issue::new('The @var tag must have a type.', $firstVar->nameSpan));

            return;
        }

        [$type, $rest] = Docblocks::splitType($content);
        if ($type !== null && preg_match('/^\$/', $rest) === 1) {
            $issue = Issue::new(
                'Do not repeat the property name after the type in the @var tag.',
                $firstVar->contentSpan(),
            );
            $name = self::nameAfterType($firstVar, $type, self::declaredNames($context));
            $context->report($name === null ? $issue : $issue->withEdit(TextEdit::delete($name)));
        }
    }

    /**
     * The span of the name, with the space before it, when it follows the
     * type on the tag's first line and is the declaration's only property. A
     * description after the name stays. With another name, or several
     * properties in one declaration, the analyzers read the tag differently
     * once the name goes.
     *
     * @param list<string> $declared
     */
    private static function nameAfterType(DocblockTag $tag, string $type, array $declared): ?Span
    {
        if (count($declared) !== 1) {
            return null;
        }

        $line = $tag->lines[0];
        $indent = strspn($line->text, characters: " \t");
        if (substr($line->text, $indent, strlen($type)) !== $type) {
            return null;
        }

        $matches = [];
        $end = $indent + strlen($type);
        $pattern = '/\G[ \t]+' . preg_quote($declared[0], delimiter: '/') . '(?=[ \t]|$)/';
        if (preg_match($pattern, $line->text, $matches, offset: $end) !== 1) {
            return null;
        }

        return new Span($line->offset + $end, $line->offset + $end + strlen($matches[0]));
    }

    /**
     * The variables the declaration declares, such as `$a` and `$b` in
     * `public $a = 0, $b = 1;`. Default values hold no variables, and quoted
     * text is skipped.
     *
     * @return list<string>
     */
    private static function declaredNames(LintContext $context): array
    {
        $text = (string) preg_replace(
            '/\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"/s',
            replacement: '',
            subject: $context->file->getText($context->node),
        );
        $matches = [];
        preg_match_all('/\$[A-Za-z_\x80-\xff][\w\x80-\xff]*/', $text, $matches);

        return array_values($matches[0]);
    }

    /**
     * Whether a property declaration has a native type hint. A native type
     * hint makes an explicit `@var` optional.
     *
     * The check reads the parsed structure, not the declaration's text. A
     * text check must skip the modifier keywords and an attribute above the
     * property (`#[Attr]`) by hand. A native type hint is a `Hint` node
     * directly under the `PlainProperty`/`HookedProperty` node, next to the
     * modifiers and attributes and not nested inside them. That is why a
     * check of the direct children is enough here.
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
