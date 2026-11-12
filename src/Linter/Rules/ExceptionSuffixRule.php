<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Syntax\Nodes;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

use function str_ends_with;

/**
 * Reports an exception class whose name does not end in `Exception`.
 *
 * An exception class is one whose parent's name ends in `Exception`. The
 * rule reads the name in the `extends` clause, not the ancestry, so a class
 * that extends a wrongly named exception isn't reported, and that parent
 * is. Coder doesn't have such a sniff.
 *
 * @internal
 */
final class ExceptionSuffixRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/exception-suffix',
            name: 'Exception suffix',
            description: "Reports an exception class whose name doesn't end in Exception.",
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Class_],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $parent = Nodes::clauseNames($file, $context->node, NodeKind::Extends)[0] ?? '';
        $identifier = str_ends_with($parent, 'Exception') ? Nodes::declaredIdentifier($file, $context->node) : null;
        if ($identifier === null) {
            return;
        }

        $name = $file->getText($identifier);
        if (str_ends_with($name, 'Exception')) {
            return;
        }

        $context->report(Issue::new(
            "The exception class {$name} must have a name that ends in Exception.",
            $identifier->span,
        ));
    }
}
