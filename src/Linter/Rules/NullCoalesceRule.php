<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Syntax\Operands;
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

use function array_filter;
use function array_values;
use function count;
use function in_array;
use function str_contains;
use function strtolower;
use function trim;

/**
 * Reports a ternary that a null coalesce operator can replace.
 *
 * Ports SlevomatCodingStandard.ControlStructures.RequireNullCoalesceOperator.
 * Two shapes match: `isset(X) ? X : B` and a strict null comparison with X
 * on one side and the other branch equal to X. Operands match by syntax tree,
 * so quotes, spacing and parentheses around an operand don't matter. A
 * condition in parentheses isn't reported, as with the sniff.
 *
 * The rule reports every match and fixes only the ones that keep behavior: X is
 * a plain variable, property, index or constant read, and B doesn't need
 * parentheses after `??`. The fix of `isset(X) ? X : B` is safe when X is a
 * variable, a constant or a static property, or one of those with one
 * property or index step, as in `$a->b` or `$a['k']`. `isset()` and `??`
 * both run `__isset` and then `__get`, or `offsetExists` and then
 * `offsetGet`, on that step. It is potentially unsafe when X reads a
 * property or an index before its last step or in an index, as in
 * `$o->data['value']`, since the ternary runs that read twice and `??` runs
 * it once. The fix of a null check is safe when X is a variable, a constant
 * or a static property. It is potentially unsafe when X reads a property or
 * an index anywhere in it, since `??` runs `__isset` or `offsetExists` and
 * the null check doesn't. A fix that drops a comment is potentially unsafe.
 *
 * The two shapes and the checks before a fix need many branches.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class NullCoalesceRule implements Rule
{
    private const MESSAGE = 'Use the null coalescing operator (??) instead of this ternary.';

    /**
     * Casts that keep the truthiness of the `isset` result.
     */
    private const SCALAR_CASTS = ['bool', 'boolean', 'int', 'integer', 'float', 'double', 'real', 'string', 'binary'];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/null-coalesce',
            name: 'Null coalesce',
            description: 'Reports an isset() or strict null check in a ternary that "??" replaces.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Conditional],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $parts = Operands::expressions($file, $context->node);

        // A short ternary doesn't have a middle part.
        if (count($parts) !== 3) {
            return;
        }

        [$condition, $then, $else] = $parts;

        // The sniff looks for `?` right after the isset() or the null check,
        // so a condition in parentheses doesn't match.
        if (Operands::isParenthesized($file, $condition)) {
            return;
        }

        $match = $this->matchIsset($file, $condition, $then, $else) ?? $this->matchNullCheck(
            $file,
            $condition,
            $then,
            $else,
        );
        if ($match === null) {
            return;
        }

        $issue = Issue::new(self::MESSAGE, Operands::questionMark($file, $condition, $then));
        $edits = $match['fixable'] ? $this->edits($context->node, $match) : [];
        if ($edits !== []) {
            $safety = $match['member'] || Operands::dropsComment($file, $edits)
                ? Safety::PotentiallyUnsafe
                : Safety::Safe;
            foreach ($edits as [$span, $text]) {
                $issue = $issue->withEdit(TextEdit::replace($span, $text)->withSafety($safety));
            }
        }

        $context->report($issue);
    }

    /**
     * Matches `isset(X) ? X : B`, with at most one scalar cast on the isset.
     *
     * @return array{keep: string, operand: Node, branch: Node, fixable: bool, member: bool}|null
     */
    private function matchIsset(SourceFile $file, Node $condition, Node $then, Node $else): ?array
    {
        $core = Operands::core($file, $condition);
        $cast = false;
        if ($core->kind === NodeKind::UnaryPrefix) {
            $cast = true;
            [$operator, $operand] = $this->prefixParts($file, $core);
            if ($operator === null || $operand === null || !$this->isScalarCast($file, $operator)) {
                return null;
            }

            $core = Operands::core($file, $operand);
        }

        if ($core->kind !== NodeKind::Construct) {
            return null;
        }

        $isset = $file->getChildren($core)[0] ?? null;
        if ($isset === null || $isset->kind !== NodeKind::IssetConstruct) {
            return null;
        }

        $arguments = Operands::expressions($file, $isset);
        if (
            count($arguments) !== 1
            || Operands::signature($file, $arguments[0]) !== Operands::signature($file, $then)
        ) {
            return null;
        }

        $operand = Operands::core($file, $then);

        return [
            'keep' => 'then',
            'operand' => $operand,
            'branch' => $else,
            'fixable' => !$cast && Operands::isPure($file, $operand) && Operands::bindsTighter($file, $else),
            // isset() and ?? both run __isset and then __get, or
            // offsetExists and then offsetGet, on the last step. The isset
            // shape runs the reads before it twice.
            'member' => Operands::readsInnerMember($file, $operand),
        ];
    }

    /**
     * Matches `X === null ? B : X` and `X !== null ? X : B`, either way round.
     *
     * @return array{keep: string, operand: Node, branch: Node, fixable: bool, member: bool}|null
     */
    private function matchNullCheck(SourceFile $file, Node $condition, Node $then, Node $else): ?array
    {
        $core = Operands::core($file, $condition);
        if ($core->kind !== NodeKind::Binary) {
            return null;
        }

        $children = $file->getChildren($core);
        if (count($children) !== 3 || $children[1]->kind !== NodeKind::BinaryOperator) {
            return null;
        }

        $operator = trim($file->getText($children[1]));
        if ($operator !== '===' && $operator !== '!==') {
            return null;
        }

        $leftIsNull = Operands::isNull($file, $children[0]);
        if ($leftIsNull === Operands::isNull($file, $children[2])) {
            return null;
        }

        $compared = $leftIsNull ? $children[2] : $children[0];
        $operand = Operands::core($file, $compared);
        if (!Operands::isOperand($file, $operand)) {
            return null;
        }

        $same = $operator === '===' ? $else : $then;
        $branch = $operator === '===' ? $then : $else;
        if (Operands::signature($file, $compared) !== Operands::signature($file, $same)) {
            return null;
        }

        return [
            'keep' => $operator === '===' ? 'condition' : 'then',
            'operand' => $operator === '===' ? $operand : Operands::core($file, $then),
            'branch' => $branch,
            'fixable' => Operands::isPure($file, $operand) && Operands::bindsTighter($file, $branch),
            // ?? runs __isset or offsetExists, which the null check doesn't.
            'member' => Operands::readsMember($file, $operand),
        ];
    }

    /**
     * The edits that turn the ternary into `X ?? B`.
     *
     * @param array{keep: string, operand: Node, branch: Node, fixable: bool, member: bool} $match
     *
     * @return list<array{Span, string}>
     */
    private function edits(Node $ternary, array $match): array
    {
        $operand = $match['operand']->span;
        $branch = $match['branch']->span;
        $whole = $ternary->span;

        // The operand copy that stays is the middle part, or the one in the
        // condition. Everything around it goes, and the text between it and
        // B becomes the operator.
        $edits = [];
        if ($operand->start > $whole->start) {
            $edits[] = [new Span($whole->start, $operand->start), ''];
        }

        if ($match['keep'] === 'then') {
            $edits[] = [new Span($operand->end, $branch->start), ' ?? '];

            return $edits;
        }

        $edits[] = [new Span($operand->end, $branch->start), ' ?? '];
        $edits[] = [new Span($branch->end, $whole->end), ''];

        return $edits;
    }

    /**
     * @return array{Node|null, Node|null}
     */
    private function prefixParts(SourceFile $file, Node $prefix): array
    {
        $children = $file->getChildren($prefix);
        $operators = array_filter(
            $children,
            static fn(Node $child): bool => $child->kind === NodeKind::UnaryPrefixOperator,
        );

        return [array_values($operators)[0] ?? null, Operands::expressions($file, $prefix)[0] ?? null];
    }

    private function isScalarCast(SourceFile $file, Node $operator): bool
    {
        $text = $file->getText($operator);
        if (!str_contains($text, '(')) {
            return false;
        }

        $name = strtolower(trim($text, characters: "() \t\r\n"));

        return in_array($name, self::SCALAR_CASTS, strict: true);
    }
}
