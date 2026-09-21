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
use function preg_match;
use function str_contains;

/**
 * Reports a use statement that imports a class with no namespace.
 *
 * Ports Drupal.Classes.UseGlobalClass. Drupal writes `\Exception` at the call
 * site and does not import it. The import and its usages move together.
 */
final class RedundantUseRule implements Rule
{
    /**
     * A statement that imports one name with no namespace, with or without an
     * alias.
     */
    private const SINGLE_GLOBAL_IMPORT = '/^use\s+\\\\?[A-Za-z_][A-Za-z0-9_]*(?:\s+as\s+[A-Za-z_][A-Za-z0-9_]*)?\s*;/i';

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/redundant-use',
            name: 'Redundant use statement',
            description: 'Reports a use statement that imports a class from the global namespace.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Use],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;

        // A single namespaced import, a grouped import and a function or
        // constant import cannot name a global class. Together they are
        // nearly every use statement. The text shows this without a node
        // read. The rule still walks a statement that lists several names.
        $text = $file->getText($context->node);
        if (!str_contains($text, ',') && preg_match(self::SINGLE_GLOBAL_IMPORT, $text) !== 1) {
            return;
        }

        // `use function` and `use const` parse as a TypedUseItemSequence. A
        // grouped import parses as a Mixed/TypedUseItemList, and its items are
        // under a namespace prefix. Only a plain sequence can import a global
        // class.
        $items = $file->getFirstDescendant($context->node, NodeKind::UseItems);
        $sequence = $items === null ? null : $file->getChildren($items)[0] ?? null;
        if ($sequence === null || $sequence->kind !== NodeKind::UseItemSequence) {
            return;
        }

        foreach ($file->getDescendants($sequence, NodeKind::UseItem) as $item) {
            // Only the item's own identifier shows whether the class is
            // namespaced. An alias is also a local identifier. A search of
            // the descendants reports `use Bar\Baz as Qux` as global.
            $identifier = $file->getFirstDescendant($item, NodeKind::Identifier);
            $name = $identifier === null ? null : $file->getChildren($identifier)[0] ?? null;
            if ($name === null) {
                continue;
            }

            // `use \Exception;` is the same global import with a leading
            // backslash. It parses as a fully qualified identifier.
            $class = ltrim($file->getText($name), characters: '\\');
            $global =
                $name->kind === NodeKind::LocalIdentifier
                || $name->kind === NodeKind::FullyQualifiedIdentifier && !str_contains($class, '\\');
            if (!$global) {
                continue;
            }
            $context->report(Issue::new("Do not import the global class {$class}.", $item->span)->withHelp(
                "Remove the use statement and write \\{$class} at each usage. "
                . 'If you remove only the import, the references break.',
            ));
        }
    }
}
