<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Calls;
use amateescu\MagoDrupal\Internal\DeprecatedDeclaration;
use amateescu\MagoDrupal\Internal\DrupalFile;
use amateescu\MagoDrupal\Internal\FileGate;
use amateescu\MagoDrupal\Internal\TopLevel;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function strlen;

/**
 * Reports a global constant: a top-level `const` statement, and a top-level
 * `define()` call in a `.module` file.
 *
 * Ports DrupalPractice.Constants.GlobalConstant and GlobalDefine. A constant
 * in a class or interface cannot clash with another module's constant.
 */
final class GlobalConstantRule implements Rule
{
    private ?FileGate $gate = null;

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/global-constant',
            name: 'Global constant',
            description: 'Reports a top-level const statement or define() call. A constant belongs in a class or interface.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            // Program gives the walk up from each node a parent chain. The
            // other two kinds put the candidates in the target list.
            targets: [NodeKind::Program, NodeKind::Constant, NodeKind::FunctionCall],
        );
    }

    public function lint(LintContext $context): void
    {
        if ($context->node->kind !== NodeKind::Program) {
            return;
        }

        $file = $context->file;
        // A `define()` call counts only in a `.module` file that has the text.
        $wanted = DrupalFile::fromSource($file)->isModule() && $this->hasDefine($file)
            ? Calls::normalizeAll(['define'])
            : null;

        foreach ($file->getTargetNodes() as $node) {
            $span = $this->candidate($file, $node, $wanted);
            if ($span === null || TopLevel::isNested($file, $node) || DeprecatedDeclaration::isMarked($file, $node)) {
                continue;
            }

            $context->report(Issue::new(
                'Global constants should not be used, move it to a class or interface.',
                $span,
            )->withHelp('A constant in a class or interface cannot clash with the constants of other modules.'));
        }
    }

    /**
     * Returns the span to report for a `const` statement or a `define()`
     * call, or NULL for any other node.
     *
     * @param null|array<string, true> $wanted The names of the calls to
     *   report, or NULL when no call counts.
     */
    private function candidate(SourceFile $file, Node $node, ?array $wanted): ?Span
    {
        if ($node->kind === NodeKind::Constant) {
            return new Span($node->span->start, $node->span->start + strlen('const'));
        }

        if (
            $wanted === null
            || $node->kind !== NodeKind::FunctionCall
            || Calls::matchWanted($file, $node, $wanted) === null
        ) {
            return null;
        }

        return new Span($node->span->start, $node->span->start + strlen((string) Calls::writtenNameFast($file, $node)));
    }

    /**
     * Whether the file has any text that can be a `define()` call. A
     * `.module` file with no such text skips the walk over its calls.
     */
    private function hasDefine(SourceFile $file): bool
    {
        $this->gate ??= new FileGate(needles: ['define']);

        return $this->gate->passes($file);
    }
}
