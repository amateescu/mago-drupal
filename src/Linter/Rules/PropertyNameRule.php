<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;

use function preg_match;
use function str_contains;
use function substr;

/**
 * Reports class properties that are not lowerCamelCase.
 *
 * Ports Drupal.NamingConventions.ValidVariableName.LowerCamelName.
 */
final class PropertyNameRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/property-name',
            name: 'Property name',
            description: 'Reports class properties that do not use lowerCamelCase.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Property],
        );
    }

    public function lint(LintContext $context): void
    {
        // Only the declared items are property names. A hooked property
        // keeps its get and set bodies in the same subtree. A walk of every
        // descendant reports the local variables inside them. A default
        // value can be a large array, and a walk of it is not worth the
        // time. The items are one level under the plain or hooked wrapper.
        foreach ($this->items($context) as $item) {
            $variable = $context->file->getFirstDescendant($item, NodeKind::DirectVariable);
            if ($variable === null) {
                continue;
            }

            $name = substr($context->file->getText($variable), offset: 1);
            if ($name === '' || preg_match('/^[a-z]/', $name) === 1 && !str_contains($name, '_')) {
                continue;
            }

            $context->report(Issue::new("Write the property \${$name} in lowerCamelCase.", $variable->span));
        }
    }

    /**
     * The property's declared items, read from the wrapper's children.
     *
     * @return list<Node>
     */
    private function items(LintContext $context): array
    {
        $items = [];
        foreach ($context->file->getChildren($context->node) as $child) {
            if ($child->kind === NodeKind::PropertyItem) {
                $items[] = $child;

                continue;
            }

            foreach ($context->file->getChildren($child) as $grandchild) {
                if ($grandchild->kind !== NodeKind::PropertyItem) {
                    continue;
                }

                $items[] = $grandchild;
            }
        }

        return $items;
    }
}
