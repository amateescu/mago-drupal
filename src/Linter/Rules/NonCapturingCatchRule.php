<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\PHPVersion;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function in_array;
use function preg_match;

/**
 * Reports a `catch` variable that nothing reads, which PHP 8.0 lets the
 * clause leave out.
 *
 * Ports SlevomatCodingStandard.Exceptions.RequireNonCapturingCatch, which
 * core's `phpcs.xml.dist` runs. As in the sniff, the variable counts as used
 * when its name appears in the catch block, in the `finally` block, or
 * anywhere after the whole `try` statement in the same function. A name
 * inside a nested function or class doesn't count, and a name in a
 * closure's `use` list or an arrow function does. The fix removes the
 * variable, so a function that may read it by another way, through
 * `compact()`, `get_defined_vars()` or a variable variable, is skipped.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class NonCapturingCatchRule implements Rule
{
    /**
     * Kinds whose body has variables of its own.
     */
    private const OTHER_SCOPES = [
        NodeKind::Function,
        NodeKind::Method,
        NodeKind::Closure,
        NodeKind::Class_,
        NodeKind::Interface,
        NodeKind::Trait,
        NodeKind::Enum,
        NodeKind::AnonymousClass,
    ];

    /**
     * Kinds that hold a function's statements, and so end the walk up to
     * the scope of the `try`.
     */
    private const FUNCTION_LIKE = [NodeKind::Function, NodeKind::Method, NodeKind::Closure];

    /**
     * Code that reads variables by a name built at run time.
     */
    private const DYNAMIC_READ = '/\bcompact\s*\(|\bget_defined_vars\s*\(|\$\$|\$\{/i';

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/non-capturing-catch',
            name: 'Non-capturing catch',
            description: 'Reports a catch variable that nothing reads, which the clause can leave out from PHP 8.0.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            // Program gives the whole file, so the code after a `try` and
            // the function around it are in reach. Try puts the statements
            // in the target list. Its own dispatch does nothing.
            targets: [NodeKind::Program, NodeKind::Try],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        if ($context->node->kind !== NodeKind::Program || !$file->phpVersion->isAtLeast(PHPVersion::fromParts(8, 0))) {
            return;
        }

        foreach ($file->getTargetNodes() as $try) {
            if ($try->kind !== NodeKind::Try) {
                continue;
            }

            $this->checkTry($context, $try);
        }
    }

    private function checkTry(LintContext $context, Node $try): void
    {
        $file = $context->file;
        $scope = $this->scopeOf($file, $try);
        if (preg_match(self::DYNAMIC_READ, $file->getText($scope)) === 1) {
            return;
        }

        $finally = null;
        $catches = [];
        foreach ($file->getChildren($try) as $child) {
            if ($child->kind === NodeKind::TryFinallyClause) {
                $finally = $child;
            }

            if ($child->kind === NodeKind::TryCatchClause) {
                $catches[] = $child;
            }
        }

        foreach ($catches as $catch) {
            $this->checkCatch($context, $catch, $finally, $scope, $try->span->end);
        }
    }

    private function checkCatch(LintContext $context, Node $catch, ?Node $finally, Node $scope, int $tryEnd): void
    {
        $file = $context->file;
        $hint = null;
        $variable = null;
        $block = null;
        foreach ($file->getChildren($catch) as $child) {
            match ($child->kind) {
                NodeKind::Hint => $hint = $child,
                NodeKind::DirectVariable => $variable = $child,
                NodeKind::Block => $block = $child,
                default => null,
            };
        }

        if ($hint === null || $variable === null || $block === null) {
            return;
        }

        $name = $file->getText($variable);
        if (
            $this->reads($file, $block, $name, start: 0)
            || $finally !== null && $this->reads($file, $finally, $name, start: 0)
            || $this->reads($file, $scope, $name, start: $tryEnd)
        ) {
            return;
        }

        $context->report(Issue::new(
            "Nothing reads the catch variable {$name}. Leave it out.",
            $variable->span,
        )->withEdit(TextEdit::delete(new Span($hint->span->end, $variable->span->end))));
    }

    /**
     * Whether the name appears in the node's own scope at or after the
     * offset, or in a closure's `use` list there.
     */
    private function reads(SourceFile $file, Node $node, string $name, int $start): bool
    {
        foreach ($file->getDescendants($node, NodeKind::DirectVariable) as $candidate) {
            if (
                $candidate->span->start >= $start
                && $file->getText($candidate) === $name
                && $this->inSameScope($file, $candidate, $node)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether no other function or class lies between the variable and the
     * node. A closure's `use` list belongs to the code around the closure.
     */
    private function inSameScope(SourceFile $file, Node $variable, Node $node): bool
    {
        $parent = $file->getParent($variable);
        while ($parent !== null && $parent->id !== $node->id) {
            if ($parent->kind === NodeKind::ClosureUseClause) {
                return true;
            }

            if (in_array($parent->kind, self::OTHER_SCOPES, strict: true)) {
                return false;
            }

            $parent = $file->getParent($parent);
        }

        return true;
    }

    /**
     * The function, method or closure that holds the `try`, or the file.
     */
    private function scopeOf(SourceFile $file, Node $try): Node
    {
        $parent = $file->getParent($try);
        while ($parent !== null) {
            if (in_array($parent->kind, self::FUNCTION_LIKE, strict: true) || $parent->kind === NodeKind::Program) {
                return $parent;
            }

            $parent = $file->getParent($parent);
        }

        return $try;
    }
}
