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

use function preg_replace;
use function str_ends_with;
use function str_starts_with;
use function strtolower;

/**
 * Reports a function whose name is not lower case.
 *
 * Ports the InvalidName check of Drupal.NamingConventions.ValidFunctionName.
 * Coder wants only lower case, so a private helper such as
 * `_mymodule_helper()` is fine. Mago's `function-name` wants snake case and
 * reports it. Methods are left to Mago's `method-name`.
 */
final class FunctionNameRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/function-name',
            name: 'Function name',
            description: 'Reports functions whose name is not lower case.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            // The Program pass reads the functions from the target list. Only
            // its snapshot has the classes around a function. The Function
            // dispatches do nothing.
            targets: [NodeKind::Program, NodeKind::Function],
        );
    }

    public function lint(LintContext $context): void
    {
        if ($context->node->kind !== NodeKind::Program) {
            return;
        }

        // An API file documents hooks with names such as
        // hook_ENTITY_TYPE_insert(), which Coder lets through.
        $apiFile = str_ends_with(strtolower($context->file->path), '.api.php');
        foreach ($context->file->getTargetNodes() as $function) {
            if ($function->kind !== NodeKind::Function) {
                continue;
            }

            $identifier = Nodes::declaredIdentifier($context->file, $function);
            if ($identifier === null) {
                continue;
            }

            // Most names are lower case, so the walk up to a class runs only
            // for the others. Coder checks only functions outside a class.
            $name = $context->file->getText($identifier);
            if (
                $name === strtolower($name)
                || $apiFile && str_starts_with($name, 'hook_')
                || Nodes::isNestedInside($context->file, $function, $context->node, Nodes::CLASS_LIKE)
            ) {
                continue;
            }

            // Coder's suggestion: an underscore before each capital that
            // follows another character, then lower case.
            $expected = strtolower((string) preg_replace('/([^_])([A-Z])/', replacement: '$1_$2', subject: $name));
            $context->report(Issue::new(
                "The function name {$name}() must be lower case, as in {$expected}().",
                $identifier->span,
            )->withHelp('Drupal function names use lower-case letters, digits and underscores.'));
        }
    }
}
