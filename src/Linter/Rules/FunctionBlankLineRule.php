<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\CommentAttachment;
use amateescu\MagoDrupal\Internal\LineEnding;
use amateescu\MagoDrupal\Internal\SourceText;
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
use Mago\Sdk\Syntax\Trivia;
use Mago\Sdk\Syntax\TriviaKind;

use function max;
use function strcasecmp;
use function substr;
use function substr_count;

/**
 * Checks that a blank line separates a function declaration from the code
 * or comment before it.
 *
 * Ports the Before check of Squiz.WhiteSpace.FunctionSpacing for a function
 * declared as a statement. `mago format` writes the blank line after a
 * `use`, `namespace` or `declare` statement, a class and another function,
 * but not after a plain statement, such as a `define()` call or the `}` of
 * an `if`. Methods are left to the formatter.
 *
 * As in Coder, the line counts from the function's docblock, the nearest one
 * only, and its attributes. A comment directly above the line with the
 * `function` keyword turns the check off. Unlike Coder, a run of comment
 * lines right above the docblock that holds a tool comment, such as
 * `// @mago-expect` or `// phpcs:ignore`, counts with the docblock, so the
 * fix never moves a tool comment away from the function. A function that is
 * first in a block is not reported: the formatter removes a blank line after
 * the `{` or `:` that opens it. Neither is a function after the opening tag
 * of a second PHP block, which Coder skips.
 *
 * The fix adds one blank line above the preamble, and `mago format` keeps it.
 * It is left out when the function starts on the line of the code before it.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class FunctionBlankLineRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        // `Function` is a target so that Rust collects every function into
        // the file's target-node list, which the Program pass reads.
        return new RuleDefinition(
            code: 'drupal/function-blank-line',
            name: 'Function blank line',
            description: 'Reports a function declaration with no blank line between it and the code or comment before it.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program, NodeKind::Function],
        );
    }

    public function lint(LintContext $context): void
    {
        if ($context->node->kind !== NodeKind::Program) {
            return;
        }

        $functions = [];
        foreach ($context->file->getTargetNodes() as $node) {
            if ($node->kind !== NodeKind::Function) {
                continue;
            }

            $functions[] = $node;
        }

        foreach ($functions as $function) {
            $issue = self::check($context->file, $function, $functions);
            if ($issue === null) {
                continue;
            }

            $context->report($issue);
        }
    }

    /**
     * The issue for the function when a blank line doesn't come before it.
     *
     * @param list<Node> $functions Every function of the file.
     */
    private static function check(SourceFile $file, Node $function, array $functions): ?Issue
    {
        $contents = $file->contents;
        $keyword = self::keyword($file, $function);

        // Coder skips a function whose line comes right after a comment.
        $above = SourceText::lastNonBlank($contents, SourceText::lineStart($contents, $keyword));
        $comment = self::commentAt($file, $above);
        if ($comment !== null && $comment->kind !== TriviaKind::DocBlockComment) {
            return null;
        }

        $start = self::preambleStart($file, $function);
        $previous = SourceText::lastNonBlank($contents, $start);
        if ($previous < 0 || self::exempt($file, $previous, $functions)) {
            return null;
        }

        $breaks = substr_count($contents, needle: "\n", offset: $previous, length: $start - $previous);
        if ($breaks >= 2) {
            return null;
        }

        $issue = Issue::new('Put a blank line before the function.', new Span($keyword, $keyword + 8));

        return $breaks === 0
            ? $issue
            : $issue->withEdit(TextEdit::insert(SourceText::lineStart($contents, $start), LineEnding::of($contents)));
    }

    /**
     * Where the function's preamble starts: its nearest docblock and its
     * attributes, with any run of comment lines right above them that holds
     * a tool comment.
     */
    private static function preambleStart(SourceFile $file, Node $function): int
    {
        $start = $function->span->start;
        $docblock = false;
        while (true) {
            $comment = self::commentAt($file, SourceText::lastNonBlank($file->contents, $start));
            if (!$docblock && $comment !== null && $comment->kind === TriviaKind::DocBlockComment) {
                $docblock = true;
                $start = $comment->span->start;

                continue;
            }

            $run = self::toolRunStart($file, $start);
            if ($run === null) {
                return $start;
            }

            $start = $run;
        }
    }

    /**
     * Where the run of comment lines right above $offset starts, when one of
     * them is a tool comment, such as `// @mago-expect` or `// phpcs:ignore`.
     * Each comment of the run has a line of its own, with no blank line
     * between them.
     */
    private static function toolRunStart(SourceFile $file, int $offset): ?int
    {
        $contents = $file->contents;
        $start = null;
        $tool = false;
        while (true) {
            $comment = self::commentAt($file, SourceText::lastNonBlank($contents, $offset));
            if (
                $comment === null
                || $comment->kind === TriviaKind::DocBlockComment
                || substr_count(
                    $contents,
                    needle: "\n",
                    offset: $comment->span->end,
                    length: $offset - $comment->span->end,
                ) !== 1
                || SourceText::lastNonBlank($contents, $comment->span->start) >= SourceText::lineStart(
                    $contents,
                    $comment->span->start,
                )
            ) {
                return $tool ? $start : null;
            }

            $tool = $tool || CommentAttachment::isDirective($file, $comment);
            $start = $comment->span->start;
            $offset = $start;
        }
    }

    /**
     * Whether the code or comment at $previous lets the function go without
     * a blank line: the `{` or `:` that opens a block, the opening tag of a
     * second PHP block, or the end of another function on the same line.
     *
     * @param list<Node> $functions
     */
    private static function exempt(SourceFile $file, int $previous, array $functions): bool
    {
        if (self::commentAt($file, $previous) === null && self::opensBlock($file->contents, $previous)) {
            return true;
        }

        $line = SourceText::lineStart($file->contents, $previous);
        foreach ($functions as $other) {
            if ($other->span->end > $line && $other->span->end <= ($previous + 1)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the code that ends at $offset opens a block: a `{`, the `:` of
     * a `case` label or of the alternative syntax, or the opening tag of a
     * PHP block that doesn't start the file. `mago format` removes a blank
     * line after the first two, and Coder skips a function after the third.
     */
    private static function opensBlock(string $contents, int $offset): bool
    {
        if ($contents[$offset] === '{' || $contents[$offset] === ':') {
            return true;
        }

        return (
            $offset >= 4
            && strcasecmp(substr($contents, $offset - 4, length: 5), '<?php') === 0
            && substr_count($contents, needle: "\n", offset: 0, length: $offset) > 0
        );
    }

    /**
     * The offset of the `function` keyword, after the attributes.
     */
    private static function keyword(SourceFile $file, Node $function): int
    {
        $end = $function->span->start;
        foreach ($file->getChildren($function) as $child) {
            if ($child->kind !== NodeKind::AttributeList) {
                continue;
            }

            $end = max($end, $child->span->end);
        }

        return SourceText::skipBlank($file->contents, $end);
    }

    /**
     * The comment that holds the offset, or null.
     */
    private static function commentAt(SourceFile $file, int $offset): ?Trivia
    {
        if ($offset < 0) {
            return null;
        }

        $trivia = $file->getTrivia();
        $index = CommentAttachment::lastTriviaIndexStartingBefore($trivia, $offset);
        $comment = $index === null ? null : $trivia[$index];

        return $comment !== null && $comment->span->end > $offset ? $comment : null;
    }
}
