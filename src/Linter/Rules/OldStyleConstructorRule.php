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

use function strtolower;

/**
 * Reports a method named after its class in a class with no __construct().
 *
 * Ports Generic.NamingConventions.ConstructorName.OldStyle. PHP 4 ran such a
 * method as the constructor. PHP 8 doesn't, and PHP never did in a
 * namespaced class, so the name only misleads.
 */
final class OldStyleConstructorRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/old-style-constructor',
            name: 'Old-style constructor',
            description: 'Reports a method named after its class in a class without __construct().',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            // Coder checks classes only. An anonymous class has no name to
            // match.
            targets: [NodeKind::Class_],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $className = strtolower(Nodes::declaredName($file, $context->node) ?? '');
        if ($className === '') {
            return;
        }

        $match = null;
        foreach ($file->getChildren($context->node) as $member) {
            $method = $member->kind === NodeKind::ClassLikeMember ? $file->getChildren($member)[0] ?? null : null;
            if ($method === null || $method->kind !== NodeKind::Method) {
                continue;
            }

            $identifier = Nodes::declaredIdentifier($file, $method);
            $name = $identifier === null ? '' : strtolower($file->getText($identifier));
            if ($name === '__construct') {
                return;
            }

            if ($name === $className) {
                $match = $identifier;
            }
        }

        if ($match === null) {
            return;
        }

        $name = $file->getText($match);
        $context->report(Issue::new(
            "The method {$name}() has the name of its class, but PHP doesn't run it as the constructor.",
            $match->span,
        )->withHelp('Rename it to __construct() if it sets up the object, or give it another name.'));
    }
}
