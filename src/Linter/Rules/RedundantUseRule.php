<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

use function ltrim;
use function str_contains;

/**
 * Reports use statements importing a class that has no namespace.
 *
 * Ports Drupal.Classes.UseGlobalClass. Drupal writes `\Exception` at the call
 * site rather than importing it, so the import and its usages move together.
 */
final class RedundantUseRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/redundant-use',
            name: 'Redundant use statement',
            description: 'Reports use statements that import a class from the global namespace.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Use],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;

        // `use function` and `use const` parse as a TypedUseItemSequence, and
        // grouped imports as a Mixed/TypedUseItemList whose items hang off a
        // namespace prefix. Only a plain sequence can import a global class.
        $items = $file->getFirstDescendant($context->node, NodeKind::UseItems);
        $sequence = $items === null ? null : $file->getChildren($items)[0] ?? null;
        if ($sequence === null || $sequence->kind !== NodeKind::UseItemSequence) {
            return;
        }

        foreach ($file->getDescendants($sequence, NodeKind::UseItem) as $item) {
            // Only the item's own identifier says whether the class is
            // namespaced. An alias is a local identifier too, so searching
            // descendants would report `use Bar\Baz as Qux` as global.
            $identifier = $file->getFirstDescendant($item, NodeKind::Identifier);
            $name = $identifier === null ? null : $file->getChildren($identifier)[0] ?? null;
            if ($name === null) {
                continue;
            }

            // `use \Exception;` spells the same global import with a leading
            // backslash and parses as a fully qualified identifier.
            $class = ltrim($file->getText($name), characters: '\\');
            $global =
                $name->kind === NodeKind::LocalIdentifier
                || $name->kind === NodeKind::FullyQualifiedIdentifier && !str_contains($class, '\\');
            if (!$global) {
                continue;
            }
            $context->report(Issue::new(
                "{$class} is not namespaced and should not be imported.",
                $item->span,
            )->withHelp(
                "Drop the use statement and write \\{$class} at each usage. "
                . 'Removing the import on its own breaks the references.',
            ));
        }
    }
}
