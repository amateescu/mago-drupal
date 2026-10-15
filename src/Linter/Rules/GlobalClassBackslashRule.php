<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Nodes;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function count;
use function in_array;
use function str_contains;
use function str_ends_with;
use function strtolower;
use function trim;

/**
 * Reports a global class written without its leading backslash in a file
 * with no namespace, as `new DateTime()` for `new \DateTime()`.
 *
 * Drupal's coding standards say that a class whose name doesn't have a
 * backslash is always written fully qualified, and show it in a `.module` file.
 * In a file with no namespace both forms name the same class, so the fix adds
 * the backslash. A name that a `use` statement imports resolves to a namespaced
 * class and is left alone. An API file is skipped: its examples leave out their
 * imports, so a short name there stands for a namespaced class. Coder doesn't
 * have such a sniff.
 *
 * The places where a class name can stand need many branches.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class GlobalClassBackslashRule implements Rule
{
    /**
     * Kinds whose identifier child is a class name.
     */
    private const CLASS_CLAUSES = [
        NodeKind::Hint,
        NodeKind::Extends,
        NodeKind::Implements,
        NodeKind::TraitUse,
        NodeKind::Attribute,
    ];

    /**
     * Kinds whose expression child is a class name when it is a bare name.
     */
    private const CLASS_OPERATIONS = [
        NodeKind::Instantiation,
        NodeKind::StaticMethodCall,
        NodeKind::StaticMethodPartialApplication,
        NodeKind::ClassConstantAccess,
        NodeKind::StaticPropertyAccess,
    ];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/global-class-backslash',
            name: 'Global class backslash',
            description: 'Reports a global class written without a leading backslash in a file with no namespace.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        if (str_ends_with(strtolower($file->path), '.api.php') || $file->getNodes(NodeKind::Namespace) !== []) {
            return;
        }

        foreach ($file->getNodes(NodeKind::Identifier) as $identifier) {
            $name = $file->getChildren($identifier)[0] ?? null;
            if (
                $name === null
                || $name->kind !== NodeKind::LocalIdentifier
                || !$this->isClassName($file, $identifier)
            ) {
                continue;
            }

            if (str_contains(Nodes::resolved($file, $identifier) ?? '\\', '\\')) {
                continue;
            }

            $written = trim($file->getText($name));
            $context->report(Issue::new(
                "Write {$written} with a leading backslash, as \\{$written}. A class with no namespace is written fully qualified.",
                $name->span,
            )->withEdit(TextEdit::insert($name->span->start, '\\')));
        }
    }

    /**
     * Whether an identifier names a class: in a type, a class clause, an
     * attribute, `new`, a static access or on the right of `instanceof`.
     */
    private function isClassName(SourceFile $file, Node $identifier): bool
    {
        $parent = $file->getParent($identifier);
        if ($parent === null) {
            return false;
        }

        if (in_array($parent->kind, self::CLASS_CLAUSES, strict: true)) {
            return true;
        }

        $operation = $parent->kind === NodeKind::Expression ? $file->getParent($parent) : null;
        if ($operation === null) {
            return false;
        }

        if (in_array($operation->kind, self::CLASS_OPERATIONS, strict: true)) {
            return true;
        }

        return $operation->kind === NodeKind::Binary && $this->isInstanceofClass($file, $operation, $parent);
    }

    /**
     * Whether the expression is the class on the right of `instanceof`.
     */
    private function isInstanceofClass(SourceFile $file, Node $binary, Node $expression): bool
    {
        $children = $file->getChildren($binary);

        return (
            count($children) === 3
            && $children[1]->kind === NodeKind::BinaryOperator
            && strtolower(trim($file->getText($children[1]))) === 'instanceof'
            && $children[2]->span->start === $expression->span->start
        );
    }
}
