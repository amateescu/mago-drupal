<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Nodes;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

use function str_starts_with;

/**
 * Reports a method name that starts with one underscore.
 *
 * Ports PSR2.Methods.MethodDeclaration.Underscore, which Coder 9's Drupal
 * standard enables. An underscore once marked a method as private; the
 * visibility keyword does that now. Two underscores start a magic method
 * and are fine.
 */
final class MethodNameUnderscoreRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/method-name-underscore',
            name: 'Method name underscore',
            description: 'Reports method names that start with an underscore to mark visibility.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Method],
        );
    }

    public function lint(LintContext $context): void
    {
        $identifier = Nodes::declaredIdentifier($context->file, $context->node);
        if ($identifier === null) {
            return;
        }

        $name = $context->file->getText($identifier);
        if (!str_starts_with($name, '_') || str_starts_with($name, '__')) {
            return;
        }

        $context->report(Issue::new(
            "Do not start the method name {$name}() with an underscore to mark its visibility.",
            $identifier->span,
        ));
    }
}
