<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Nodes;
use amateescu\MagoDrupal\Internal\SwitchCases;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function count;
use function in_array;
use function preg_match;
use function strlen;
use function strpos;
use function strrpos;
use function strspn;
use function substr;

/**
 * Reports a `return;` that is the last statement of a function body.
 *
 * Ports Squiz.PHP.NonExecutableCode.ReturnNotRequired. A function, method
 * or closure ends the same way with or without the statement. A `return;`
 * that ends a `{ }` block at the end of the body counts too. An `if`, loop,
 * `switch` or `try` that ends with `return;` does not, and neither does an
 * arrow function, since it has no statements.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class RedundantReturnRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/redundant-return',
            name: 'Redundant return',
            description: 'Reports a `return;` that is the last statement of a function body.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Function, NodeKind::Method, NodeKind::Closure],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $body = Nodes::body($file, $context->node);
        $return = $body === null ? null : self::lastReturn($file, $body);
        if ($return === null) {
            return;
        }

        $keyword = $file->getChildren($return)[0] ?? $return;
        $issue = Issue::new('Empty return statement not required here.', $keyword->span);
        $edit = self::edit($file->contents, $return);
        $context->report($edit === null ? $issue : $issue->withEdit($edit));
    }

    /**
     * Returns the `return;` that ends the body, looking through the `{ }`
     * blocks that end it.
     */
    private static function lastReturn(SourceFile $file, Node $body): ?Node
    {
        $block = $body->kind === NodeKind::MethodBody ? $file->getChildren($body)[0] ?? $body : $body;
        $statements = Nodes::statements($file, $block);
        $last = $statements[count($statements) - 1] ?? null;
        $inner = $last === null || $block->kind !== NodeKind::Block ? null : SwitchCases::inner($file, $last);
        if ($inner?->kind === NodeKind::Block) {
            return self::lastReturn($file, $inner);
        }

        // A value after the keyword makes a third child. A comment does not
        // make a child.
        return $inner?->kind === NodeKind::Return && count($file->getChildren($inner)) === 2 ? $inner : null;
    }

    /**
     * Builds the edit that deletes the statement, or null when a comment
     * sits inside it. The line goes with it when the statement is alone on
     * its line.
     */
    private static function edit(string $contents, Node $return): ?TextEdit
    {
        $start = $return->span->start;
        $end = $return->span->end;
        if (preg_match('/^return\s*;$/i', substr($contents, $start, $end - $start)) !== 1) {
            return null;
        }

        // A search with a negative offset does not copy the prefix.
        $newline = strrpos($contents, needle: "\n", offset: $start - strlen($contents));
        $lineStart = $newline === false ? 0 : $newline + 1;
        $before = substr($contents, $lineStart, $start - $lineStart);
        $afterBlank = $end + strspn($contents, characters: " \t", offset: $end);
        $lineBreak = strpos($contents, needle: "\n", offset: $afterBlank);
        $alone =
            strspn($before, characters: " \t") === strlen($before)
            && in_array($contents[$afterBlank] ?? "\n", ["\n", "\r"], strict: true);
        if (!$alone) {
            return TextEdit::delete(new Span($start, $afterBlank));
        }

        return TextEdit::delete(new Span($lineStart, $lineBreak === false ? strlen($contents) : $lineBreak + 1));
    }
}
