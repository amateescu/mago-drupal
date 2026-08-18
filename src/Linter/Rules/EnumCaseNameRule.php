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
 * Ports Drupal.NamingConventions.ValidEnumCase, which applies the class naming
 * rules to case names.
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
        $problem = $this->problem($name);
        if ($problem === null) {
            return;
        }

        $context->report(Issue::new($problem, $identifier->span));
    }

    /**
     * Returns how $name departs from UpperCamelCase, if it does.
     */
    private function problem(string $name): ?string
    {
        if (preg_match('/^[A-Z]/', $name) !== 1) {
            return "Enum case {$name} must begin with a capital letter.";
        }

        if (str_contains($name, '_')) {
            return "Enum case {$name} must use UpperCamelCase without underscores.";
        }

        if (preg_match('/^[A-Z]{3}[^a-z]*$/', $name) === 1) {
            return "Enum case {$name} must not be several upper-case letters in a row.";
        }

        return null;
    }
}
