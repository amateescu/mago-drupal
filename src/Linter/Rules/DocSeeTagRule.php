<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Comment\CommentAttachment;
use amateescu\MagoDrupal\Internal\Docblock\Docblocks;
use amateescu\MagoDrupal\Internal\Docblock\FunctionTags;
use amateescu\MagoDrupal\Internal\Docblock\OuterDocblocks;
use amateescu\MagoDrupal\Internal\Docblock\PropertyTags;
use amateescu\MagoDrupal\Internal\Docblock\SeeTags;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_key_exists;

/**
 * Checks that each `@see` tag holds one reference on its own line, with no
 * other text and no punctuation at the end.
 *
 * Ports the `@see` codes of Drupal.Commenting.FunctionComment and
 * Drupal.Commenting.VariableComment. Coder reads the tags of function and
 * property docblocks only. The standard asks the same of every `@see` tag,
 * so the rule also checks the references in the other docblocks outside a
 * function body.
 */
final class DocSeeTagRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/doc-see-tag',
            name: 'Doc see tag',
            description: 'Reports a @see tag with no reference, or with text or punctuation after the reference.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program, NodeKind::Function, NodeKind::Method, NodeKind::Property],
        );
    }

    public function lint(LintContext $context): void
    {
        if ($context->node->kind === NodeKind::Program) {
            self::checkOtherDocblocks($context);

            return;
        }

        if ($context->node->kind === NodeKind::Property) {
            self::checkProperty($context);

            return;
        }

        $tags = FunctionTags::of($context);
        if ($tags !== null) {
            SeeTags::check($context, $tags->tags);
        }
    }

    /**
     * Reports a `@see` tag with nothing after it on its line. Coder wants the
     * reference on the tag's own line, and doesn't check anything else of a
     * `@see` in a property docblock. The Program dispatch checks the
     * references.
     */
    private static function checkProperty(LintContext $context): void
    {
        SeeTags::checkEmpty($context, PropertyTags::of($context) ?? []);
    }

    /**
     * Checks the `@see` references of the docblocks outside a function body
     * that are not a function's or a method's.
     */
    private static function checkOtherDocblocks(LintContext $context): void
    {
        $functionDocblocks = self::functionDocblocks($context->file);
        foreach (OuterDocblocks::of($context->file) as $span) {
            if (array_key_exists($span->start, $functionDocblocks) || OuterDocblocks::isGroup($context->file, $span)) {
                continue;
            }

            SeeTags::checkReferences($context, Docblocks::tags($context->file, $span));
        }
    }

    /**
     * The start offsets of the docblocks of functions and methods.
     *
     * @return array<int, true>
     */
    private static function functionDocblocks(SourceFile $file): array
    {
        // The rule targets functions and methods, so they are in the file's
        // target nodes. A getNodes() call per kind scans the whole tree.
        $starts = [];
        foreach ($file->getTargetNodes() as $function) {
            if ($function->kind !== NodeKind::Function && $function->kind !== NodeKind::Method) {
                continue;
            }

            $docblock = CommentAttachment::attachedTo($file, CommentAttachment::commentAnchor($file, $function));
            if ($docblock === null) {
                continue;
            }

            $starts[$docblock->start] = true;
        }

        return $starts;
    }
}
