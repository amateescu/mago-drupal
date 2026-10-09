<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\CaseEnding;
use amateescu\MagoDrupal\Internal\Nodes;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_filter;
use function array_values;
use function count;
use function substr;
use function trim;

/**
 * Reports a non-empty case that falls through to the next case with no comment.
 *
 * Ports the TerminatingComment check of PSR2.ControlStructures.SwitchDeclaration.
 * A case is fine when a comment of any kind sits right before the next
 * label, when a `break`, `continue`, `return`, `throw`, `exit`, `die` or
 * `goto` ends it, or when its last statement ends it through `if` and
 * `else` branches, `try` blocks or a nested `switch`. A `default` case is
 * never reported, and neither is the last case.
 */
final class CaseFallThroughRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/case-fall-through',
            name: 'Case fall through',
            description: 'Reports a non-empty switch case that falls through to the next case with no comment.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Switch],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;

        $children = $file->getChildren($context->node);
        $body = $file->getChildren($children[count($children) - 1] ?? $context->node)[0] ?? null;
        if ($body === null) {
            return;
        }

        $cases = array_values(array_filter(
            $file->getChildren($body),
            static fn(Node $child): bool => $child->kind === NodeKind::SwitchCase,
        ));
        foreach ($cases as $index => $case) {
            $label = $file->getChildren($case)[0] ?? null;
            $next = $cases[$index + 1] ?? null;
            if ($label === null || $label->kind !== NodeKind::SwitchExpressionCase || $next === null) {
                continue;
            }

            if (self::fallsThrough($file, $label, $next)) {
                $keyword = $file->getChildren($label)[0] ?? $label;
                $context->report(Issue::new(
                    'There must be a comment when fall-through is intentional in a non-empty case body.',
                    $keyword->span,
                )->withHelp('End the case with `break`, or add a comment before the next case.'));
            }
        }
    }

    /**
     * Whether the case has statements, no comment before the next case, and
     * no statement that ends it.
     */
    private static function fallsThrough(SourceFile $file, Node $label, Node $next): bool
    {
        $statements = Nodes::statements($file, $label);
        $last = $statements[count($statements) - 1] ?? null;

        if ($last === null) {
            return false;
        }

        // The text between the last statement and the next case holds only
        // comments, which Coder takes as the note of any fall-through.
        $from = $last->span->end;
        $gap = substr($file->contents, $from, $next->span->start - $from);

        return trim($gap) === '' && !CaseEnding::leaves($file, $statements);
    }
}
