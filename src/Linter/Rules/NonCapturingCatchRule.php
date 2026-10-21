<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\CodeMask;
use amateescu\MagoDrupal\Internal\SourceText;
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
use function substr;

/**
 * Reports a `catch` variable that's never read, which PHP 8.0 lets the
 * clause leave out.
 *
 * Ports SlevomatCodingStandard.Exceptions.RequireNonCapturingCatch, which
 * core's `phpcs.xml.dist` runs. As in the sniff, the variable counts as used
 * when its name appears in the catch block, in the `finally` block, or
 * anywhere after the whole `try` statement in the same function. A name
 * inside a nested function or class doesn't count, and a name in a
 * closure's `use` list or an arrow function does. The rule also counts the
 * name as read where the sniff doesn't: anywhere in a loop around the `try`,
 * since the next pass reads it, and anywhere in the function as a reference
 * or a `global` or `static` name, whose value code outside the function
 * sees. The fix removes the variable, so a function that may read it by
 * another way, through `compact()`, `get_defined_vars()` or a variable
 * variable, is skipped.
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
     * Kinds of a loop.
     */
    private const LOOPS = [NodeKind::For, NodeKind::Foreach, NodeKind::While, NodeKind::DoWhile];

    /**
     * Code that reads variables by a name built at run time.
     */
    private const DYNAMIC_READ = '/\b(?:compact|get_defined_vars)' . SourceText::GAP . '\(|\$\$|\$\{/i';

    /**
     * A `&` at the end of a parameter's text before its variable, with any
     * space or comments after it.
     */
    private const BY_REFERENCE = '/&' . SourceText::GAP . '$/';

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/non-capturing-catch',
            name: 'Non-capturing catch',
            description: 'Reports a catch variable that\'s never read, which the clause can leave out from PHP 8.0.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            // Program gives the whole file, so the code after a `try` and
            // the function around it are in reach. Try puts the statements
            // in the target list. Its own dispatch doesn't do anything.
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
        // A match in the raw text is checked again on CodeMask's code, so a
        // comment or a string that names compact() doesn't count.
        $scope = $this->scopeOf($file, $try);
        if (
            preg_match(self::DYNAMIC_READ, $file->getText($scope)) === 1
            && preg_match(self::DYNAMIC_READ, substr(
                CodeMask::code($file->contents),
                $scope->span->start,
                $scope->span->length(),
            )) === 1
        ) {
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
            $this->checkCatch($context, $catch, $finally, $scope, $try);
        }
    }

    private function checkCatch(LintContext $context, Node $catch, ?Node $finally, Node $scope, Node $try): void
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
            || $this->reads($file, $scope, $name, start: $try->span->end)
            || $this->readsElsewhere($file, $scope, $variable, $this->outerLoop($file, $try, $scope))
        ) {
            return;
        }

        $context->report(Issue::new(
            "The catch variable {$name} is never read. Leave it out.",
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
     * Whether other code can read what the catch writes. That is the name
     * anywhere in a loop around the `try`, other than as a catch variable,
     * since the next pass runs that code again. It is also the name anywhere
     * in the scope as a reference or a `global` or `static` name, which code
     * outside the function sees.
     */
    private function readsElsewhere(SourceFile $file, Node $scope, Node $variable, ?Node $loop): bool
    {
        $name = $file->getText($variable);
        foreach ($file->getDescendants($scope, NodeKind::DirectVariable) as $candidate) {
            $parent = $file->getParent($candidate);
            if (
                $parent === null
                || $candidate->id === $variable->id
                || $file->getText($candidate) !== $name
                || !$this->inSameScope($file, $candidate, $scope)
            ) {
                continue;
            }

            if (
                $loop !== null && $parent->kind !== NodeKind::TryCatchClause && $loop->span->contains($candidate->span)
                || $this->isShared($file, $candidate, $parent)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a variable shares its value with code outside the function: a
     * `&$e` parameter, a `use (&$e)` variable, a `global` or `static` name,
     * `&$e` as in `$x = &$e` or `foreach ($items as &$e)`, and the left side
     * of `$e = &$x`.
     */
    private function isShared(SourceFile $file, Node $variable, Node $parent): bool
    {
        $contents = $file->contents;
        // A variadic `&...$e` is a new array of references. A write to $e
        // replaces the array and doesn't reach the caller.
        if ($parent->kind === NodeKind::FunctionLikeParameter) {
            $before = substr($contents, $parent->span->start, $variable->span->start - $parent->span->start);

            return preg_match(self::BY_REFERENCE, $before) === 1;
        }

        if ($parent->kind === NodeKind::ClosureUseClauseVariable) {
            return $contents[$parent->span->start] === '&';
        }

        if ($parent->kind === NodeKind::StaticAbstractItem || $parent->kind === NodeKind::StaticConcreteItem) {
            return true;
        }

        $holder = $parent->kind === NodeKind::Variable ? $file->getParent($parent) : null;
        if ($holder?->kind === NodeKind::Global) {
            return true;
        }

        $context = $holder?->kind === NodeKind::Expression ? $file->getParent($holder) : null;
        if ($context?->kind === NodeKind::UnaryPrefix) {
            return $contents[$context->span->start] === '&';
        }

        if ($holder === null || $context?->kind !== NodeKind::Assignment) {
            return false;
        }

        $right = $file->getChildren($context)[2] ?? null;
        $value = $right === null ? null : $file->getChildren($right)[0] ?? null;

        return (
            $holder->span->start === $context->span->start
            && $value?->kind === NodeKind::UnaryPrefix
            && $contents[$value->span->start] === '&'
        );
    }

    /**
     * The outermost `for`, `foreach`, `while` or `do` loop around the `try`
     * in its scope, or null.
     */
    private function outerLoop(SourceFile $file, Node $try, Node $scope): ?Node
    {
        $loop = null;
        $parent = $file->getParent($try);
        while ($parent !== null && $parent->id !== $scope->id) {
            if (in_array($parent->kind, self::LOOPS, strict: true)) {
                $loop = $parent;
            }

            $parent = $file->getParent($parent);
        }

        return $loop;
    }

    /**
     * Whether the variable and the node aren't separated by another function or
     * class. A closure's `use` list belongs to the code around the closure.
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
