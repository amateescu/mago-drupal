<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
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
        // Only the declared items are property names. A hooked property keeps
        // its get and set bodies in the same subtree, so walking every
        // descendant would report the local variables inside them.
        foreach ($context->file->getDescendants($context->node, NodeKind::PropertyItem) as $item) {
            $variable = $context->file->getFirstDescendant($item, NodeKind::DirectVariable);
            if ($variable === null) {
                continue;
            }

            $name = substr($context->file->getText($variable), offset: 1);
            if ($name === '' || preg_match('/^[a-z]/', $name) === 1 && !str_contains($name, '_')) {
                continue;
            }

            $context->report(Issue::new("Property \${$name} must use lowerCamelCase.", $variable->span));
        }
    }
}
