<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Operands;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\Safety;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function count;
use function preg_match;
use function trim;

/**
 * Reports a ternary that the short ternary operator replaces.
 *
 * Ports SlevomatCodingStandard.ControlStructures.RequireShortTernaryOperator,
 * which core's `phpcs.xml.dist` runs. Two shapes match: `X ? X : B` and
 * `!X ? B : X`. Both become `X ?: B`. Operands match by syntax tree, as in
 * `drupal/null-coalesce`.
 *
 * The fix runs only when X is a plain variable, property, index or constant
 * read, because `X ?: B` reads X once where the ternary reads it twice. In the
 * second shape B moves to the end, so it also needs to bind tighter than the
 * ternary. A fix that drops a comment is potentially unsafe.
 *
 * The two shapes and the checks before a fix need many branches.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class ShortTernaryRule implements Rule
{
    private const MESSAGE = 'Use the short ternary operator (?:) instead of repeating the condition.';

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/short-ternary',
            name: 'Short ternary',
            description: 'Reports a ternary that repeats its condition, which "?:" replaces.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Conditional],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $ternary = $context->node;
        $parts = Operands::expressions($file, $ternary);

        // A short ternary has no middle part.
        if (count($parts) !== 3) {
            return;
        }

        [$condition, $then, $else] = $parts;
        $edits = null;
        if (Operands::signature($file, $condition) === Operands::signature($file, $then)) {
            $edits = Operands::isPure($file, Operands::core($file, $then))
                ? [[new Span($condition->span->end, $else->span->start), ' ?: ']]
                : [];
        }

        $negated = $edits === null ? $this->negatedOperand($file, $condition) : null;
        if ($negated !== null) {
            if (Operands::signature($file, $negated) !== Operands::signature($file, $else)) {
                return;
            }

            $edits = Operands::isPure($file, Operands::core($file, $negated)) && Operands::bindsTighter($file, $then)
                ? [
                    [new Span($ternary->span->start, $negated->span->start), ''],
                    [new Span($negated->span->end, $then->span->start), ' ?: '],
                    [new Span($then->span->end, $ternary->span->end), ''],
                ]
                : [];
        }

        if ($edits === null) {
            return;
        }

        $issue = Issue::new(self::MESSAGE, Operands::questionMark($file, $condition, $then));
        $dropped = '';
        foreach ($edits as [$span]) {
            $dropped .= $file->getText($span);
        }

        $safety = preg_match('~/\*|//|#~', $dropped) === 1 ? Safety::PotentiallyUnsafe : Safety::Safe;
        foreach ($edits as [$span, $text]) {
            $issue = $issue->withEdit(TextEdit::replace($span, $text)->withSafety($safety));
        }

        $context->report($issue);
    }

    /**
     * The X of a condition written as `!X`, or null.
     */
    private function negatedOperand(SourceFile $file, Node $condition): ?Node
    {
        $core = Operands::core($file, $condition);
        if ($core->kind !== NodeKind::UnaryPrefix) {
            return null;
        }

        foreach ($file->getChildren($core) as $child) {
            if ($child->kind === NodeKind::UnaryPrefixOperator && trim($file->getText($child)) !== '!') {
                return null;
            }
        }

        return Operands::expressions($file, $core)[0] ?? null;
    }
}
