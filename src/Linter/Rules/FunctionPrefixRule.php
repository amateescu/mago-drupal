<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\DrupalFile;
use amateescu\MagoDrupal\Internal\Nodes;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;

use function str_starts_with;
use function strlen;
use function substr;

/**
 * Reports a function in a `.module` file whose name does not start with
 * the module name.
 *
 * Ports the InvalidPrefix check of Drupal.NamingConventions.ValidFunctionName.
 * Functions share one global namespace. The module name keeps them apart.
 */
final class FunctionPrefixRule implements Rule
{
    /**
     * Name prefixes that Coder lets through: preprocess functions and theme
     * functions.
     */
    private const EXEMPT_PREFIXES = ['template_preprocess', 'theme'];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/function-prefix',
            name: 'Function prefix',
            description: 'Reports functions in a .module file whose name does not start with the module name.',
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

        // Coder checks only `.module` files, not `.install` or `.inc` files.
        $file = DrupalFile::fromSource($context->file);
        if (!$file->isModule()) {
            return;
        }

        foreach ($context->file->getTargetNodes() as $function) {
            if (
                $function->kind !== NodeKind::Function
                || Nodes::isNestedInside($context->file, $function, $context->node, Nodes::CLASS_LIKE)
            ) {
                continue;
            }

            $this->check($context, $file, $function);
        }
    }

    /**
     * Reports one function whose name lacks the module prefix.
     */
    private function check(LintContext $context, DrupalFile $file, Node $function): void
    {
        $identifier = Nodes::declaredIdentifier($context->file, $function);
        if ($identifier === null) {
            return;
        }

        $name = $context->file->getText($identifier);
        foreach (self::EXEMPT_PREFIXES as $exempt) {
            if (str_starts_with($name, $exempt)) {
                return;
            }
        }

        // A leading underscore marks a private helper, as in `_foo_helper()`.
        // The prefix needs at least one character after it.
        $prefix = $file->name . '_';
        $bare = str_starts_with($name, '_') ? substr($name, offset: 1) : $name;
        if (strlen($bare) > strlen($prefix) && str_starts_with($bare, $prefix)) {
            return;
        }

        $context->report(Issue::new(
            "The function {$name}() must start with the module name, as in {$prefix}{$name}().",
            $identifier->span,
        )->withHelp('Functions share one global namespace. The module name keeps them apart.'));
    }
}
