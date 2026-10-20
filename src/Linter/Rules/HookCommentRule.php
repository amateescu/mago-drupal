<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\DocblockLine;
use amateescu\MagoDrupal\Internal\HookDocblock;
use amateescu\MagoDrupal\Internal\HookSummary;
use amateescu\MagoDrupal\Internal\Nodes;
use amateescu\MagoDrupal\Internal\TopLevel;
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

use function ltrim;
use function preg_match;
use function preg_quote;
use function strlen;

/**
 * Checks the "Implements hook_x()." docblock convention on a function that
 * is declared at the top level of a file.
 *
 * Ports Drupal.Commenting.HookComment. Like the sniff, the rule skips a
 * function inside a block, a braced namespace, another function or a class.
 * Only the docblock right above the `function` keyword counts, so a comment
 * or an attribute between the two hides it. `drupal/hook-method-comment`
 * checks `#[Hook]` methods.
 */
final class HookCommentRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/hook-comment',
            name: 'Hook comment',
            description: 'Checks the "Implements hook_x()." docblock convention on a hook implementation.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program, NodeKind::Function],
        );
    }

    public function lint(LintContext $context): void
    {
        // Function is a target for `TopLevel::functions()`. Its own
        // dispatches don't do anything.
        if ($context->node->kind !== NodeKind::Program) {
            return;
        }

        foreach (TopLevel::functions($context->file) as $function) {
            $this->checkFunction($context, $function);
        }
    }

    private function checkFunction(LintContext $context, Node $function): void
    {
        $docblock = HookDocblock::above($context->file, $function);
        $summary = $docblock === null ? [] : HookDocblock::summary($context->file, $docblock);
        if ($docblock === null || $summary === []) {
            return;
        }

        if (HookSummary::check($context, $docblock, $summary)) {
            return;
        }

        $this->checkRepeat($context, $function, HookSummary::text($summary), $summary);
    }

    /**
     * Reports a description that only repeats the function's own name. The
     * fix writes the hook name when the function starts with the name of the
     * extension that owns the file.
     *
     * @param list<DocblockLine> $summary
     */
    private function checkRepeat(LintContext $context, Node $function, string $short, array $summary): void
    {
        $name = Nodes::declaredName($context->file, $function);
        $found = [];
        $pattern = '/^\s*Implements (' . preg_quote((string) $name, delimiter: '/') . ')\(\)\.\s*$/i';
        if ($name === null || preg_match($pattern, $short, $found) !== 1) {
            return;
        }

        $issue = Issue::new(
            'Document a hook implementation with "Implements hook_example().".',
            HookSummary::span($summary),
        )->withHelp('Replace the repeated function name with the abstract hook_ name that it implements.');
        $hook = HookDocblock::hookName($context->file, $found[1]);
        if ($hook === null) {
            $context->report($issue);

            return;
        }

        $first = $summary[0];
        $start = $first->offset + strlen($first->text) - strlen(ltrim($first->text));
        // A hook with a placeholder, such as hook_ENTITY_TYPE_insert(), gets
        // the function's own words in place of the placeholder, so the fix
        // is potentially unsafe.
        $context->report($issue->withEdit(TextEdit::replace(
            new Span($start, HookSummary::span($summary)->end),
            'Implements ' . $hook . '().',
        )->withSafety(Safety::PotentiallyUnsafe)));
    }
}
