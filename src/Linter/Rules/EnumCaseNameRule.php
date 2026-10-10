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

use function preg_match;
use function str_contains;

/**
 * Reports enum cases that are not UpperCamelCase.
 *
 * Ports Drupal.NamingConventions.ValidEnumCase. The sniff applies the class
 * naming rules to case names.
 */
final class EnumCaseNameRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/enum-case-name',
            name: 'Enum case name',
            description: 'Reports enum cases that do not use UpperCamelCase.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::EnumCase],
        );
    }

    public function lint(LintContext $context): void
    {
        $identifier = Nodes::declaredIdentifier($context->file, $context->node);
        if ($identifier === null) {
            return;
        }

        $name = $context->file->getText($identifier);
        foreach ($this->problems($name) as $problem) {
            $context->report(Issue::new($problem, $identifier->span));
        }
    }

    /**
     * Returns each way $name differs from UpperCamelCase.
     *
     * @return list<string>
     */
    private function problems(string $name): array
    {
        // Coder reports each failing check under its own code, so one name
        // can get several issues.
        $problems = [];
        if (preg_match('/^[A-Z]/', $name) !== 1) {
            $problems[] = "Enum case {$name} must start with a capital letter.";
        }

        if (str_contains($name, '_')) {
            $problems[] = "Enum case {$name} must use UpperCamelCase without underscores.";
        }

        if (preg_match('/^[A-Z]{3}[^a-z]*$/', $name) === 1) {
            $problems[] = "Enum case {$name} must not have several upper-case letters in a row.";
        }

        return $problems;
    }
}
