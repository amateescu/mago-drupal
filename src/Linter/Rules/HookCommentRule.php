<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\DocblockLine;
use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\HookDocblock;
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

use function count;
use function ltrim;
use function preg_match;
use function preg_quote;
use function str_contains;
use function strlen;

/**
 * Checks the "Implements hook_x()." docblock convention on a function that
 * is declared at the top level of a file.
 *
 * Ports Drupal.Commenting.HookComment. Like the sniff, the rule skips a
 * function inside a block, a braced namespace, another function or a class.
 * Only the docblock right above the `function` keyword counts, so a comment
 * or an attribute between the two hides it.
 */
final class HookCommentRule implements Rule
{
    private const IMPLEMENTS_HOOK = '/^Implement[^\n]+?hook_[^\n]+/i';

    private const WELL_FORMED = '/ (drush_)?hook_[a-zA-Z0-9_]+\(\)( for .+)?\.$/';

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/hook-comment',
            name: 'Hook comment',
            description: 'Checks the "Implements hook_x()." docblock convention on a hook implementation.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
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

        $short = $this->text($summary);
        if (preg_match(self::IMPLEMENTS_HOOK, $short) === 1) {
            $this->checkImplementsHook($context, $docblock, $short, $summary);

            return;
        }

        $this->checkRepeat($context, $function, $short, $summary);
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
            $this->span($summary),
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
            new Span($start, $this->span($summary)->end),
            'Implements ' . $hook . '().',
        )->withSafety(Safety::PotentiallyUnsafe)));
    }

    /**
     * @param list<DocblockLine> $summary
     */
    private function checkImplementsHook(LintContext $context, Span $span, string $short, array $summary): void
    {
        $wellFormed =
            str_contains($short, 'Implements ')
            && !str_contains($short, 'Implements of')
            && preg_match(self::WELL_FORMED, $short) === 1;

        if (!$wellFormed) {
            $context->report(Issue::new(
                'Use the format "Implements hook_foo().", "Implements hook_foo_BAR_ID_bar() for xyz_bar().", or a similar hook_ reference.',
                $this->span($summary),
            ));

            return;
        }

        foreach (Docblocks::tags($context->file, $span) as $tag) {
            if ($tag->name === 'param') {
                $context->report(Issue::new(
                    'Do not repeat the @param documentation in a hook implementation.',
                    $tag->nameSpan,
                ));
            }

            if ($tag->name === 'return') {
                $context->report(Issue::new(
                    'Do not repeat the @return documentation in a hook implementation.',
                    $tag->nameSpan,
                ));
            }
        }
    }

    /**
     * Joins a paragraph's lines into one string with nothing between them,
     * as Coder's sniff does.
     *
     * @param list<DocblockLine> $paragraph
     */
    private function text(array $paragraph): string
    {
        $text = '';
        foreach ($paragraph as $line) {
            $text .= $line->text;
        }

        return $text;
    }

    /**
     * @param list<DocblockLine> $paragraph
     */
    private function span(array $paragraph): Span
    {
        $last = $paragraph[count($paragraph) - 1];

        return new Span($paragraph[0]->offset, $last->offset + strlen($last->text));
    }
}
