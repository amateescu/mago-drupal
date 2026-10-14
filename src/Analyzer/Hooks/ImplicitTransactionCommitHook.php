<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Internal\Expressions;
use amateescu\MagoDrupal\Internal\TestFiles;
use Mago\Sdk\Analyzer\MethodCallAnalysisHook;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function count;
use function in_array;
use function strtolower;
use function trim;

/**
 * Reports a database transaction that is committed by going out of scope.
 *
 * Drupal 11.3 added `Transaction::commitOrRelease()`, and 11.5 deprecates
 * committing by letting the transaction object go out of scope. A
 * `startTransaction()` result that is thrown away commits at once. One kept
 * in a local variable is reported when the function never calls
 * `commitOrRelease()` on that variable and never hands the variable on:
 * returning it, passing it to a call, storing it elsewhere or capturing it in
 * a closure all let other code commit it. A later standalone `rollBack()` in
 * the same straight-line block ends the transaction too. Without
 * `commitOrRelease()` in the codebase there is nothing better to call, so
 * nothing is reported.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class ImplicitTransactionCommitHook implements MethodCallAnalysisHook
{
    /**
     * Mago prefixes hook codes with the plugin identifier, so this is reported
     * as `drupal/implicit-transaction-commit`.
     */
    public const CODE = 'implicit-transaction-commit';

    private const CONNECTION = 'Drupal\Core\Database\Connection';

    private const TRANSACTION = 'Drupal\Core\Database\Transaction';

    /**
     * Uses of the variable that neither commit it nor hand it on.
     */
    private const LOCAL_USES = [NodeKind::Unset, NodeKind::IssetConstruct];

    private const METHOD_CALLS = [NodeKind::MethodCall, NodeKind::NullSafeMethodCall];

    /**
     * Nodes with variables of their own.
     */
    private const NESTED = [NodeKind::Closure, NodeKind::Function, NodeKind::Method];

    public function getTargets(): array
    {
        return [MethodTarget::exact(self::CONNECTION, 'startTransaction')];
    }

    public function getRequirements(): array
    {
        return [];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        // Tests of the transaction API rely on the destructor on purpose.
        if (
            TestFiles::isTestOrHookDocumentation($context->analysis->file)
            || !$context->codebase->methodExists(self::TRANSACTION, 'commitOrRelease')
        ) {
            return;
        }

        $file = $context->analysis->getSourceFile();
        $span = $context->node->span;
        $call = Expressions::at($file, NodeKind::MethodCall, $span) ?? Expressions::at(
            $file,
            NodeKind::NullSafeMethodCall,
            $span,
        );
        if ($call === null) {
            return;
        }

        [$value, $parent] = Expressions::unwrap($file, $call);
        if ($parent?->kind === NodeKind::ExpressionStatement) {
            $context->report(
                Level::Warning,
                self::CODE,
                Issue::new(
                    'The result of startTransaction() is thrown away, so the transaction is committed right away.',
                    $context->node->span,
                    'transaction not kept',
                )->withHelp(
                    'Keep the transaction in a variable and call commitOrRelease() on it once the work is done.',
                ),
            );

            return;
        }

        $target = self::assignedVariable($file, $value, $parent);
        if ($target === null || self::handedOnOrCommitted($file, $call, $target)) {
            return;
        }

        $variable = $file->getText($target);
        $context->report(
            Level::Warning,
            self::CODE,
            Issue::new(
                "The transaction in {$variable} is committed when the variable goes out of scope, which Drupal deprecates from 11.5.",
                $context->node->span,
                'committed by going out of scope',
            )->withHelp("Call {$variable}->commitOrRelease() once the work in the transaction is done."),
        );
    }

    /**
     * The variable of a plain `$name = <value>;` statement. A chained or
     * nested assignment hands the value on, so it gets null.
     */
    private static function assignedVariable(SourceFile $file, Node $value, ?Node $assignment): ?Node
    {
        if ($assignment === null || $assignment->kind !== NodeKind::Assignment) {
            return null;
        }

        $children = $file->getChildren($assignment);
        if (count($children) !== 3 || $children[2]->id !== $value->id || trim($file->getText($children[1])) !== '=') {
            return null;
        }

        [, $statement] = Expressions::unwrap($file, $assignment);

        return $statement?->kind === NodeKind::ExpressionStatement
            ? Expressions::directVariable($file, $children[0])
            : null;
    }

    /**
     * Whether the scope holding the call ends the transaction explicitly or
     * hands the variable on.
     */
    private static function handedOnOrCommitted(SourceFile $file, Node $call, Node $target): bool
    {
        $name = $file->getText($target);
        $scope = Expressions::scopeOf($file, $call);
        foreach ($file->getDescendants($scope, NodeKind::DirectVariable) as $use) {
            if ($use->id === $target->id || $file->getText($use) !== $name || self::nested($file, $use, $scope)) {
                continue;
            }

            [$expression, $parent] = Expressions::unwrap($file, $use);
            if (!self::staysLocal($file, $expression, $parent, $call)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a use sits in a closure, function or method inside the scope,
     * whose own variable of that name is another one. A capture in a
     * closure's `use` clause belongs to the scope, and an arrow function
     * shares its variables.
     */
    private static function nested(SourceFile $file, Node $use, Node $scope): bool
    {
        foreach ($file->getAncestors($use) as $ancestor) {
            if ($ancestor->id === $scope->id || $ancestor->kind === NodeKind::ClosureUseClause) {
                return false;
            }

            if (in_array($ancestor->kind, self::NESTED, strict: true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether one use of the variable neither commits it nor hands it on:
     * unsetting it, checking it with isset(), overwriting it, or calling a
     * method that leaves the transaction active.
     */
    private static function staysLocal(SourceFile $file, Node $expression, ?Node $parent, Node $started): bool
    {
        if ($parent === null) {
            return false;
        }

        if (in_array($parent->kind, self::LOCAL_USES, strict: true)) {
            return true;
        }

        $children = $file->getChildren($parent);
        if (($children[0] ?? null)?->id !== $expression->id) {
            return false;
        }

        if (in_array($parent->kind, self::METHOD_CALLS, strict: true)) {
            $selector = $children[1] ?? null;
            $method = $selector === null ? '' : strtolower(trim($file->getText($selector)));

            return (
                $method !== 'commitorrelease'
                && ($method !== 'rollback' || !self::straightRollback($file, $started, $parent))
            );
        }

        return $parent->kind === NodeKind::Assignment;
    }

    /**
     * A standalone rollback after the start, in the same block with no
     * intervening control flow that might leave it before the rollback.
     * A rollback in a catch or conditional branch cannot end the transaction
     * on the other paths, so it keeps the implicit commit report.
     */
    private static function straightRollback(SourceFile $file, Node $started, Node $rollback): bool
    {
        [, $statement] = Expressions::unwrap($file, $rollback);
        if (
            $statement === null
            || $statement->kind !== NodeKind::ExpressionStatement
            || $rollback->span->start <= $started->span->start
        ) {
            return false;
        }

        $receiver = Expressions::directVariable($file, $file->getChildren($rollback)[0]);
        if ($receiver === null) {
            return false;
        }

        $name = $file->getText($receiver);
        $block = self::statementBlock($file, $statement);
        $startBlock = null;
        foreach ($file->getAncestors($started) as $ancestor) {
            if ($ancestor->kind !== NodeKind::ExpressionStatement) {
                continue;
            }

            $startBlock = self::statementBlock($file, $ancestor);
            break;
        }

        if ($block === null || $block->id !== $startBlock?->id) {
            return false;
        }

        foreach ($file->getChildren($block) as $child) {
            if ($child->span->start <= $started->span->start || $child->span->start >= $statement->span->start) {
                continue;
            }

            $node = $child->kind === NodeKind::Statement ? $file->getChildren($child)[0] ?? $child : $child;
            if ($node->kind !== NodeKind::ExpressionStatement || self::replacesVariable($file, $node, $name)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Overwriting or unsetting the variable ends the earlier transaction by
     * destruction; a later rollback belongs to a different value.
     */
    private static function replacesVariable(SourceFile $file, Node $statement, string $name): bool
    {
        foreach ($file->getDescendants($statement, NodeKind::DirectVariable) as $variable) {
            if ($file->getText($variable) !== $name) {
                continue;
            }

            [$value, $parent] = Expressions::unwrap($file, $variable);
            if ($parent === null) {
                continue;
            }

            if (
                $parent->kind === NodeKind::Unset
                || $parent->kind === NodeKind::Assignment
                && ($file->getChildren($parent)[0] ?? null)?->id === $value->id
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * The block a statement sits directly in, without a conditional body
     * between them, or the program for a top-level statement.
     */
    private static function statementBlock(SourceFile $file, Node $statement): ?Node
    {
        $parent = $file->getParent($statement);
        if ($parent?->kind === NodeKind::Statement) {
            $parent = $file->getParent($parent);
        }

        return $parent !== null && in_array($parent->kind, [NodeKind::Block, NodeKind::Program], strict: true)
            ? $parent
            : null;
    }
}
