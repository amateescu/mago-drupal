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

/**
 * Reports a class, interface, trait or enum name that starts with three
 * capitals and has no lower-case letter, such as `HTTP`.
 *
 * Ports Drupal.NamingConventions.ValidClassName.NoUpperAcronyms. The test is
 * the one `drupal/enum-case-name` uses for case names.
 */
final class ClassNameAcronymRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/class-name-acronym',
            name: 'Class name acronym',
            description: 'Reports class-like names that start with three capitals and have no lower-case letter, such as HTTP.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Class_, NodeKind::Interface, NodeKind::Trait, NodeKind::Enum],
        );
    }

    public function lint(LintContext $context): void
    {
        $identifier = Nodes::declaredIdentifier($context->file, $context->node);
        if ($identifier === null) {
            return;
        }

        // The pattern works on bytes, as Coder's does. A multi-byte letter
        // after the capitals does not count as a lower-case letter.
        $name = $context->file->getText($identifier);
        if (preg_match('/^[A-Z]{3}[^a-z]*$/', $name) !== 1) {
            return;
        }

        $kind = match ($context->node->kind) {
            NodeKind::Interface => 'Interface',
            NodeKind::Trait => 'Trait',
            NodeKind::Enum => 'Enum',
            default => 'Class',
        };

        $context->report(Issue::new(
            "{$kind} {$name} must not have several upper-case letters in a row.",
            $identifier->span,
        ));
    }
}
