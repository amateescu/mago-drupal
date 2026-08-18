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
 * Reports define() constants in a procedural file that skip the module prefix.
 *
 * Ports Drupal.Semantics.ConstantName.ConstantStart. Constants defined by a
 * module share one global namespace, so the prefix is what keeps them apart.
 */
final class ConstantPrefixRule extends CallRule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/constant-prefix',
            name: 'Constant prefix',
            description: "Reports define() constants that are not prefixed with the module's name.",
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
        // The underscore is part of the prefix: CORPUSCACHE_TTL does not count
        // as prefixed for a module named corpus.
        $expected = strtoupper($file->name) . '_';
        if ($constant === null || $constant === '' || str_starts_with($constant, $expected)) {
            return;
        }

        $context->report(Issue::new(
            "Constants defined by a module must be prefixed with '{$expected}', found '{$constant}'.",
            $name->span,
        )->withHelp('Module constants share the global namespace, so the prefix keeps them from colliding.'));
    }
}
