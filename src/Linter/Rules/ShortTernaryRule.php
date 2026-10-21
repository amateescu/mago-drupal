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
use function trim;

/**
 * Reports a ternary that the short ternary operator replaces.
 *
 * Ports SlevomatCodingStandard.ControlStructures.RequireShortTernaryOperator,
 * which core's `phpcs.xml.dist` runs. Two shapes match: `X ? X : B` and
 * `!X ? B : X`. Both become `X ?: B`. Operands match by syntax tree, as in
 * `drupal/null-coalesce`. A condition in parentheses isn't reported, as with
 * the sniff, unless the middle part has parentheses too.
 *
 * The fix runs only when X is a plain variable, property, index or constant
 * read, because `X ?: B` reads X once where the ternary reads it twice. In the
 * second shape B moves to the end, so it also needs to bind tighter than the
 * ternary. The fix is safe when X is a variable, a constant or a static
 * property. It is potentially unsafe when X reads a property or an index
 * anywhere in it, since `__get` or `offsetGet` then runs once instead of
 * twice, and when it drops a comment.
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

        // A short ternary doesn't have a middle part.
        if (count($parts) !== 3) {
            return;
        }

        [$condition, $then, $else] = $parts;

        // The sniff compares the condition's text with the middle part, so
        // `(X) ? X : B` doesn't match.
        if (Operands::isParenthesized($file, $condition) && !Operands::isParenthesized($file, $then)) {
            return;
        }

        $edits = null;
        $operand = null;
        if (Operands::signature($file, $condition) === Operands::signature($file, $then)) {
            $operand = Operands::core($file, $then);
            $edits = Operands::isPure($file, $operand)
                ? [[new Span($condition->span->end, $else->span->start), ' ?: ']]
                : [];
        }

        $negated = $edits === null ? $this->negatedOperand($file, $condition) : null;
        if ($negated !== null) {
            if (Operands::signature($file, $negated) !== Operands::signature($file, $else)) {
                return;
            }

            $operand = Operands::core($file, $negated);
            $edits = Operands::isPure($file, $operand) && Operands::bindsTighter($file, $then)
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
        $safety = $operand === null ? Safety::Safe : $this->safety($file, $edits, $operand);
        foreach ($edits as [$span, $text]) {
            $issue = $issue->withEdit(TextEdit::replace($span, $text)->withSafety($safety));
        }

        $context->report($issue);
    }

    /**
     * The safety of the edits that write `X ?: B`.
     *
     * @param list<array{Span, string}> $edits
     */
    private function safety(SourceFile $file, array $edits, Node $operand): Safety
    {
        if ($edits === []) {
            return Safety::Safe;
        }

        // X ?: B reads X once, so a property or an index with __get or
        // offsetGet behind it runs that once instead of twice.
        if (Operands::readsMember($file, $operand)) {
            return Safety::PotentiallyUnsafe;
        }

        return Operands::dropsComment($file, $edits) ? Safety::PotentiallyUnsafe : Safety::Safe;
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
