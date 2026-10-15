<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\DeclarationComment;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

/**
 * Checks that a class property has a docblock.
 *
 * Ports `Missing` of Drupal.Commenting.VariableComment. Coder wants a
 * docblock on a typed property too. The type only makes its `@var` tag
 * optional. `drupal/variable-comment` reports a comment in the wrong style.
 */
final class MissingPropertyCommentRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/missing-property-comment',
            name: 'Missing property comment',
            description: 'Reports a class property with no docblock.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Property],
        );
    }

    public function lint(LintContext $context): void
    {
        if (DeclarationComment::of($context)->comment === null) {
            $context->report(Issue::new('The property doesn\'t have a docblock.', $context->node->span));
        }
    }
}
