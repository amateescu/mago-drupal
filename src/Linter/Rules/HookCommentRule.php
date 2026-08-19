<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\DocblockLine;
use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\Nodes;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\TriviaKind;

use function count;
use function preg_match;
use function preg_quote;
use function str_contains;
use function strlen;

/**
 * Checks the "Implements hook_x()." docblock convention on a top-level
 * function.
 *
 * Ports Drupal.Commenting.HookComment. `NodeKind::Function` already excludes
 * methods, which are `NodeKind::Method`, so only a function nested inside
 * another function needs its own exclusion here.
 */
final class HookCommentRule implements Rule
{
    private const IMPLEMENTS_HOOK = '/^Implement[^\n]*?hook_[^\n]+/i';

    private const WELL_FORMED = '/ (drush_)?hook_[a-zA-Z0-9_]+\(\)( for .+)?\.$/';

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/hook-comment',
            name: 'Hook comment',
            description: 'Checks the "Implements hook_x()." docblock convention on a hook implementation.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Function],
        );
    }

    public function lint(LintContext $context): void
    {
        foreach ($context->file->getAncestors($context->node) as $ancestor) {
            if ($ancestor->kind === NodeKind::Function) {
                return;
            }
        }

        $closest = Docblocks::closest($context->file, $context->node);
        if ($closest === null || $closest->kind !== TriviaKind::DocBlockComment) {
            return;
        }

        [$summary] = Docblocks::paragraphs($context->file, $closest->span);
        if ($summary === []) {
            return;
        }

        $short = $this->text($summary);
        if (preg_match(self::IMPLEMENTS_HOOK, $short) === 1) {
            $this->checkImplementsHook($context, $closest->span, $short, $summary);

            return;
        }

        $name = Nodes::declaredName($context->file, $context->node);
        if (
            $name !== null
            && preg_match('/^Implements ' . preg_quote($name, delimiter: '/') . '\(\)\.$/i', $short) === 1
        ) {
            $context->report(Issue::new(
                'Hook implementations must be documented with "Implements hook_example().".',
                $this->span($summary),
            )->withHelp('Replace the repeated function name with the abstract hook_ name it implements.'));
        }
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
                'Format should be "Implements hook_foo().", "Implements hook_foo_BAR_ID_bar() for xyz_bar().", or a similar hook_ reference.',
                $this->span($summary),
            ));

            return;
        }

        foreach (Docblocks::tags($context->file, $span) as $tag) {
            if ($tag->name === 'param') {
                $context->report(Issue::new(
                    'Hook implementations should not duplicate @param documentation.',
                    $tag->nameSpan,
                ));
            }

            if ($tag->name === 'return') {
                $context->report(Issue::new(
                    'Hook implementations should not duplicate @return documentation.',
                    $tag->nameSpan,
                ));
            }
        }
    }

    /**
     * Joins a paragraph's lines into one string, the way Coder's own sniff
     * reconstructs a short description that spans several lines.
     *
     * @param list<DocblockLine> $paragraph
     */
    private function text(array $paragraph): string
    {
        $text = $paragraph[0]->text;
        for ($index = 1; $index < count($paragraph); ++$index) {
            $text .= ' ' . $paragraph[$index]->text;
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
