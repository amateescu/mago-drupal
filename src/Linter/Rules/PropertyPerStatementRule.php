<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

/**
 * Reports a property statement that declares more than one property.
 *
 * Ports the Multiple check of PSR2.Classes.PropertyDeclaration. The rule
 * reads the names of the declaration, so a property hook is never taken for
 * a second name. A split would have to copy the attributes and sort out the
 * docblock and the comments between the names, so there is no fix.
 */
final class PropertyPerStatementRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/property-per-statement',
            name: 'Property per statement',
            description: 'Reports a statement that declares more than one property.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::PlainProperty],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $first = null;
        $count = 0;
        foreach ($file->getChildren($context->node) as $child) {
            if ($child->kind !== NodeKind::PropertyItem) {
                continue;
            }

            $first ??= $child;
            ++$count;
        }

        if ($first === null || $count < 2) {
            return;
        }

        $name = $file->getFirstDescendant($first, NodeKind::DirectVariable) ?? $first;
        $context->report(Issue::new('Declare each property in its own statement.', $name->span));
    }
}
