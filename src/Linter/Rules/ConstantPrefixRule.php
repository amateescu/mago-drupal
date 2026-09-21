<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\DrupalFile;
use amateescu\MagoDrupal\Internal\Values;
use amateescu\MagoDrupal\Linter\CallRule;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\NodeKind;

use function str_starts_with;
use function strtoupper;

/**
 * Reports define() constants in a procedural file that have no module prefix.
 *
 * Ports Drupal.Semantics.ConstantName.ConstantStart. Constants that a module
 * defines share one global namespace. The prefix keeps them apart.
 */
final class ConstantPrefixRule extends CallRule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/constant-prefix',
            name: 'Constant prefix',
            description: 'Reports define() constants that do not start with the module name.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    protected function names(): array
    {
        return ['define'];
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        $file = DrupalFile::fromSource($context->file);
        if (!$file->isModule() && !$file->isInstall()) {
            return;
        }

        $name = $this->argument($context, $call, 0);
        if ($name === null || $name->kind !== NodeKind::LiteralString) {
            return;
        }

        $constant = Values::literalString($context->file, $name);
        // The underscore is part of the prefix. For a module named corpus,
        // CORPUSCACHE_TTL does not count as prefixed.
        $expected = strtoupper($file->name) . '_';
        if ($constant === null || $constant === '' || str_starts_with($constant, $expected)) {
            return;
        }

        $context->report(Issue::new(
            "The constant '{$constant}' must start with the module prefix '{$expected}'.",
            $name->span,
        )->withHelp('Module constants share the global namespace. The prefix keeps them apart.'));
    }
}
