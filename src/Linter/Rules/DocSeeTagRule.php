<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\FunctionTags;
use amateescu\MagoDrupal\Internal\OuterDocblocks;
use amateescu\MagoDrupal\Internal\PropertyTags;
use amateescu\MagoDrupal\Internal\SeeTags;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_key_exists;
use function trim;

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
    /**
     * @param bool $core Checks only the tags of functions and methods and
     *   the empty `@see` tags of properties, as core's phpcs does.
     */
    public function __construct(
        private readonly bool $core = false,
    ) {}

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
            if (!$this->core) {
                self::checkOtherDocblocks($context);
            }

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
        foreach (PropertyTags::of($context) ?? [] as $tag) {
            if ($tag->name !== 'see' || trim($tag->lines[0]->text) !== '') {
                continue;
            }

            $context->report(Issue::new('The @see tag must have content.', $tag->nameSpan));
        }
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
        $starts = [];
        foreach ([...$file->getNodes(NodeKind::Function), ...$file->getNodes(NodeKind::Method)] as $function) {
            $docblock = Docblocks::attachedTo($file, Docblocks::commentAnchor($file, $function));
            if ($docblock === null) {
                continue;
            }

            $starts[$docblock->start] = true;
        }

        return $starts;
    }
}
