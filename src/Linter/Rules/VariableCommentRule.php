<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\CommentDocblock;
use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\DocblockTag;
use amateescu\MagoDrupal\Internal\DocType;
use amateescu\MagoDrupal\Internal\TypeNames;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\Safety;
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
use function str_ends_with;
use function stripos;
use function strlen;
use function strspn;
use function substr;
use function trim;

/**
 * Checks that a class property has a `@var` docblock.
 *
 * Ports Drupal.Commenting.VariableComment. `TypeNames` checks the `@var`
 * type.
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
        // The attributes belong to the plain or hooked property inside the
        // declaration.
        $property = $context->file->getChildren($context->node)[0] ?? $context->node;
        $anchor = Docblocks::commentAnchor($context->file, $property);
        $closest = Docblocks::closest($context->file, $anchor);

        // Coder wants a docblock on a typed property too. The type only makes
        // its @var tag optional.
        if ($closest === null) {
            $context->report(Issue::new('The property has no docblock.', $context->node->span));

            return;
        }

        if ($closest->kind !== TriviaKind::DocBlockComment) {
            $issue = Issue::new('The property docblock must start with "/**".', $context->node->span);
            $fix = CommentDocblock::edit($context->file, $closest, $property, $anchor);
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

            // Coder wants the reference on the tag's own line.
            if ($tag->name === 'see' && trim($tag->lines[0]->text) === '') {
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

        // `$this` is a type, and Coder accepts it.
        $name = [];
        if (preg_match(DocType::VARIABLE, $content, $name) === 1 && $name[0] !== '$this') {
            self::reportNameFirst($context, $firstVar, $name[0]);

            return;
        }

        [$type] = Docblocks::splitType($content);
        if ($type !== null) {
            TypeNames::checkVar($context, $firstVar, $type);
        }

        // Coder looks for the repeated name on the tag's own line only.
        $line = trim($firstVar->lines[0]->text);
        $lineType = self::lineType($line);
        if ($lineType !== null && preg_match('/^\s+\$/', substr($line, strlen($lineType))) === 1) {
            $issue = Issue::new(
                'Do not repeat the property name after the type in the @var tag.',
                $firstVar->contentSpan(),
            );
            $name = self::nameAfterType($firstVar, $lineType, self::declaredNames($context));
            $context->report($name === null ? $issue : $issue->withEdit(TextEdit::delete($name)));
        }
    }

    /**
     * The type at the start of the tag's line. A type such as
     * `array<string, int>` or `callable(int, string): bool` keeps its
     * spaces. Text that is not a whole type ends at the first space, and so
     * does `$this`.
     */
    private static function lineType(string $line): ?string
    {
        $type = DocType::leading($line);
        // A callable's return type follows its `): ` and is part of the type.
        if ($type !== null && str_ends_with($type, '):')) {
            $end = strlen($type) + strspn($line, characters: " \t", offset: strlen($type));
            $return = DocType::leading(substr($line, $end));
            if ($return !== null) {
                $type = substr($line, offset: 0, length: $end) . $return;
            }
        }

        return $type !== null && DocType::whole($type) ? $type : Docblocks::splitType($line)[0];
    }

    /**
     * Reports a `@var` tag that starts with a variable name. The fix moves
     * the type before the name, or drops the name when it is the property's
     * own, when a whole type follows the name on the tag's line.
     */
    private static function reportNameFirst(LintContext $context, DocblockTag $tag, string $name): void
    {
        $issue = Issue::new('Start the @var tag with the type, not a variable name.', $tag->contentSpan());
        $line = $tag->lines[0];
        $text = trim($line->text);
        $swapped = DocType::typeFirst($text);
        $start = $tag->typeStart($name);
        if ($swapped === null || $start === null) {
            $context->report($issue);

            return;
        }

        $edit = TextEdit::replace(new Span($start, $start + strlen($text)), $swapped);
        if (self::declaredNames($context) === [$name]) {
            $gap = strspn($line->text, characters: " \t", offset: $start - $line->offset + strlen($name));
            $edit = TextEdit::delete(new Span($start, $start + strlen($name) + $gap));
        }

        // The analyzers do not read the type in this order. Once it comes
        // first they trust it, so the fix asks first.
        $context->report($issue->withEdit($edit->withSafety(Safety::PotentiallyUnsafe)));
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

        $start = $tag->typeStart($type);
        if ($start === null) {
            return null;
        }

        $line = $tag->lines[0];
        $matches = [];
        $end = $start - $line->offset + strlen($type);
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
