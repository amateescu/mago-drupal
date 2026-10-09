<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\ModulePrefix;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_pop;
use function in_array;

/**
 * Reports top-level const constants in a procedural file that have no module prefix.
 *
 * Ports Drupal.Semantics.ConstantName.ConstConstantStart. The test is the
 * one `drupal/constant-prefix` uses for define().
 */
final class ConstPrefixRule implements Rule
{
    /**
     * The nodes that lead from the program to its top-level statements. A
     * braced namespace holds a block, which is not on this list.
     */
    private const CONTAINERS = [
        NodeKind::Statement,
        NodeKind::Namespace,
        NodeKind::NamespaceBody,
        NodeKind::NamespaceImplicitBody,
    ];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/const-prefix',
            name: 'Const prefix',
            description: 'Reports const constants that do not start with the module name.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $expected = ModulePrefix::expected($context->file);
        if ($expected === null) {
            return;
        }

        foreach ($this->statements($context->file, $context->node) as $constant) {
            $issue = $this->issue($context->file, $constant, $expected);
            if ($issue !== null) {
                $context->report($issue);
            }
        }
    }

    /**
     * Builds the issue for one const statement, if its first name has no prefix.
     */
    private function issue(SourceFile $file, Node $constant, string $expected): ?Issue
    {
        $children = $file->getChildren($constant);
        $keyword = $children[0] ?? null;
        $item = $children[1] ?? null;
        if ($keyword === null || $item === null || $keyword->kind !== NodeKind::Keyword) {
            return null;
        }

        // Only the first name of a list is checked.
        $name = $file->getFirstDescendant($item, NodeKind::LocalIdentifier);

        return $name === null ? null : ModulePrefix::issue($file->getText($name), $expected, $keyword->span);
    }

    /**
     * Returns the const statements at the top of the file.
     *
     * A statement after `namespace Foo;` counts. A statement inside a braced
     * namespace does not, as in Coder 9. PHP has const statements nowhere
     * else but at the top level of a file or of a namespace.
     *
     * @return list<Node>
     */
    private function statements(SourceFile $file, Node $program): array
    {
        $found = [];
        $stack = [$program];
        while (($node = array_pop($stack)) !== null) {
            foreach ($file->getChildren($node) as $child) {
                if (in_array($child->kind, self::CONTAINERS, strict: true)) {
                    $stack[] = $child;
                }

                if ($child->kind === NodeKind::Constant) {
                    $found[] = $child;
                }
            }
        }

        return $found;
    }
}
