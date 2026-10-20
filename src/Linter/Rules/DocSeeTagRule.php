<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\FunctionTags;
use amateescu\MagoDrupal\Internal\PropertyTags;
use amateescu\MagoDrupal\Internal\SeeTags;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

use function trim;

/**
 * Checks that each `@see` tag holds one reference on its own line, with no
 * other text and no punctuation at the end.
 *
 * Ports the `@see` codes of Drupal.Commenting.FunctionComment and
 * Drupal.Commenting.VariableComment, which read the tags of function and
 * property docblocks only. `drupal/doc-see-reference` checks the references in
 * the other docblocks.
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
            targets: [NodeKind::Function, NodeKind::Method, NodeKind::Property],
        );
    }

    public function lint(LintContext $context): void
    {
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
     * `@see` in a property docblock. `drupal/doc-see-reference` checks the
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
}
