<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\DocblockTag;
use amateescu\MagoDrupal\Internal\DocTagGroups;
use amateescu\MagoDrupal\Internal\OuterDocblocks;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

use function in_array;

/**
 * Checks that the tags of a docblock are grouped, with `@param` first, and
 * the blank lines before the tags and between their groups.
 *
 * Ports the tag group codes of Drupal.Commenting.DocComment.
 * `DocTagGroups` holds the checks on the blank lines and the `@param`
 * group.
 */
final class DocTagGroupsRule implements Rule
{
    /**
     * Tags that this rule checks for order. Each must also be in one group.
     */
    private const ORDERED_TAGS = ['param', 'return', 'throws'];

    public function getDefinition(): RuleDefinition
    {
        // Function and Method are targets for `OuterDocblocks::of()`. Their
        // own dispatches do nothing.
        return new RuleDefinition(
            code: 'drupal/doc-tag-groups',
            name: 'Doc tag groups',
            description: 'Checks that the tags of one kind are in one group, @param first, and the blank lines around the groups.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program, NodeKind::Function, NodeKind::Method],
        );
    }

    public function lint(LintContext $context): void
    {
        if ($context->node->kind !== NodeKind::Program) {
            return;
        }

        foreach (OuterDocblocks::of($context->file) as $span) {
            if (OuterDocblocks::isGroup($context->file, $span)) {
                continue;
            }

            DocTagGroups::check($context, $span);
            $this->checkTagOrder($context, Docblocks::tags($context->file, $span));
        }
    }

    /**
     * Reports a tag name that appears again after a different ordered tag.
     * `DocTagGroups` reports the `@param` groups.
     *
     * Only `@param`, `@return` and `@throws` are ordered tags. No other tag
     * counts here, and that includes Doxygen markup such as `@code`,
     * `@endcode`, `@todo` and `@link`. Such a tag does not have to be in a
     * group, and it does not split one. Coder compares with the last ordered
     * tag only.
     *
     * @param list<DocblockTag> $tags
     */
    private function checkTagOrder(LintContext $context, array $tags): void
    {
        $seen = [];
        $lastName = null;
        foreach ($tags as $tag) {
            if (!in_array($tag->name, self::ORDERED_TAGS, strict: true)) {
                continue;
            }

            if (($seen[$tag->name] ?? false) && $lastName !== $tag->name) {
                $context->report(Issue::new(
                    "Keep the @{$tag->name} tags together. Do not put other tags between them.",
                    $tag->nameSpan,
                ));
            }

            $seen[$tag->name] = true;
            $lastName = $tag->name;
        }
    }
}
