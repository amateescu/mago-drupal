<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\CommentDocblock;
use amateescu\MagoDrupal\Internal\DocblockGap;
use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\FileDocblock;
use amateescu\MagoDrupal\Internal\FileTags;
use amateescu\MagoDrupal\Internal\LineEnding;
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
use Mago\Sdk\Syntax\Trivia;
use Mago\Sdk\Syntax\TriviaKind;

use function preg_match;
use function str_contains;
use function strspn;

/**
 * Checks the docblock at the start of a PHP file: its style, its `@file`
 * tag and the blank line below it, and that a file with a namespace and one
 * class, interface, trait or enum does not start with a comment. Also checks
 * the blank line between a comment and the `declare` statement below it.
 *
 * Ports Drupal.Commenting.FileComment, with the sniff's skip of the `@file`
 * checks for a file that holds a class, and the blank line before `declare`
 * of SlevomatCodingStandard.TypeHints.DeclareStrictTypes.
 * `drupal/missing-file-comment` reports a file that does not start with a
 * comment.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class FileCommentRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/file-comment',
            name: 'File comment',
            description: "Checks the style, the @file tag and the blank line below the docblock that starts a PHP file, and that a namespaced class file doesn't start with a comment.",
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program, ...FileDocblock::DECLARATIONS],
        );
    }

    public function lint(LintContext $context): void
    {
        // The declarations are targets only so that the class file checks
        // can read them from the target-node list.
        if ($context->node->kind !== NodeKind::Program) {
            return;
        }

        $first = FileDocblock::firstComment($context->file);
        $covered = $first !== null && $this->checkFirstComment($context, $first);
        $this->checkBlankLineBeforeDeclare($context, $covered ? $first : null);
    }

    /**
     * Checks the comment at the start of the file. Returns whether an issue
     * on it also covers the line below it. That is the case for a comment
     * that must go, a comment in the wrong style and a missing blank line
     * after the docblock.
     */
    private function checkFirstComment(LintContext $context, Trivia $first): bool
    {
        // A namespaced class file has its own check, which comes before the
        // `@file` checks.
        if (FileDocblock::isNamespacedClassFile($context->file)) {
            $this->reportNamespacedFileComment($context, $first);

            return true;
        }

        if (!FileDocblock::hasOpenTag($context->file)) {
            return false;
        }

        if ($first->kind !== TriviaKind::DocBlockComment) {
            $issue = Issue::new('The file docblock must start with "/**".', $first->span);
            $fix = CommentDocblock::fileEdit($context->file, $first);

            return $this->report($context, $fix === null ? $issue : $issue->withEdit($fix));
        }

        // Tag names are case-sensitive in Coder, so `@FILE` is not a file tag.
        [$tag, $variant] = Docblocks::fileTag($context->file, $first->span);
        if ($tag !== null) {
            return $this->checkTagPosition($context, $first->span, $tag);
        }

        $issue = Issue::new('The file docblock must have an @file tag.', FileTags::reportSpan(
            $context->file->contents,
            $first->span,
        ));
        $opener = $variant ? null : FileTags::insertOffset($context->file->contents, $first->span);
        $this->report(
            $context,
            $opener === null
                ? $issue
                : $issue->withEdit(TextEdit::insert($opener, LineEnding::of($context->file->contents) . ' * @file')),
        );

        return false;
    }

    /**
     * Reports an `@file` tag that is not on the line right below the
     * docblock's opener, and checks the blank line below the docblock when
     * it is. Returns whether the blank line got a report.
     *
     * A potentially unsafe fix moves a tag that stands alone on its line.
     */
    private function checkTagPosition(LintContext $context, Span $docblock, Span $tag): bool
    {
        $contents = $context->file->contents;
        if (FileTags::onSecondLine($contents, $docblock, $tag)) {
            return $this->checkBlankLineAfter($context, $docblock);
        }

        $issue = Issue::new('The second line in the file docblock must be "@file".', FileTags::reportSpan(
            $contents,
            $docblock,
        ));
        $moved = FileTags::move($contents, $docblock, $tag);
        $this->report($context, $moved === null ? $issue : $issue->withEdit($moved));

        return false;
    }

    /**
     * Reports a file docblock with no blank line below it, and returns
     * whether it did. `mago format` already turns several blank lines into
     * one, so only a missing one is left to report. Coder skips a docblock
     * right above a `?>`.
     */
    private function checkBlankLineAfter(LintContext $context, Span $docblock): bool
    {
        $edit = DocblockGap::blankLine($context->file, $docblock);
        if ($edit === null || preg_match('/\G\s*\?>/', $context->file->contents, offset: $docblock->end) === 1) {
            return false;
        }

        $issue = Issue::new('Put a blank line after the file docblock.', new Span($docblock->end - 2, $docblock->end));

        return $this->report($context, $issue->withEdit($edit));
    }

    /**
     * Reports a comment right above the `declare(strict_types=1)` that starts
     * the code, on the line above or on the same line. Slevomat's
     * DeclareStrictTypes wants a blank line between them, and the fix adds
     * one. Unlike the file comment checks, it runs on class files too.
     * `$covered` is the file comment when another check here already reports
     * the line below it.
     */
    private function checkBlankLineBeforeDeclare(LintContext $context, ?Trivia $covered): void
    {
        $file = $context->file;
        if (!str_contains($file->contents, 'strict_types')) {
            return;
        }

        $declare = self::firstDeclare($file, $context->node);
        if ($declare === null) {
            return;
        }

        $above = null;
        foreach ($file->getTrivia() as $trivia) {
            if ($trivia->span->end > $declare->span->start) {
                break;
            }

            $above = $trivia;
        }

        if ($above === null || $covered !== null && $above->span->start === $covered->span->start) {
            return;
        }

        // Only whitespace parts the last comment from the statement, as the
        // statement is the first one after the open tag.
        $edit = DocblockGap::blankLine($file, $above->span);
        if ($edit === null) {
            return;
        }

        $issue = Issue::new('Put a blank line above the declare statement.', $declare->span);
        $context->report($issue->withEdit($edit));
    }

    /**
     * The `declare` statement right after the first open tag, when it sets
     * `strict_types=1`, or null. Slevomat checks only that one.
     */
    private static function firstDeclare(SourceFile $file, Node $program): ?Node
    {
        $open = false;
        foreach ($file->getChildren($program) as $statement) {
            $inner = $file->getChildren($statement)[0] ?? null;
            if ($inner === null) {
                return null;
            }

            if (!$open) {
                $open = $inner->kind === NodeKind::OpeningTag;

                continue;
            }

            $strict = preg_match('/\bstrict_types\s*=\s*1\b/', $file->getText($inner)) === 1;

            return $inner->kind === NodeKind::Declare && $strict ? $inner : null;
        }

        return null;
    }

    /**
     * Reports the comment at the start of a file with a namespace and one
     * class, interface, trait or enum. The fix deletes a docblock and the
     * whitespace after it. Coder has no fix for a plain comment.
     */
    private function reportNamespacedFileComment(LintContext $context, Trivia $first): void
    {
        $issue = Issue::new(
            'A file with a namespace and one class, interface, trait or enum must not start with a file comment.',
            $first->span,
        );
        if ($first->kind !== TriviaKind::DocBlockComment) {
            $context->report($issue);

            return;
        }

        // The text of the docblock is lost, so the fix is potentially unsafe.
        $end = $first->span->end + strspn($context->file->contents, characters: " \t\r\n", offset: $first->span->end);
        $edit = TextEdit::delete(new Span($first->span->start, $end))->withSafety(Safety::PotentiallyUnsafe);
        $context->report($issue->withEdit($edit));
    }

    /**
     * Reports the issue unless Coder skips the file comment of the file, and
     * returns whether it did.
     */
    private function report(LintContext $context, Issue $issue): bool
    {
        if (FileDocblock::isClassFile($context->file)) {
            return false;
        }

        $context->report($issue);

        return true;
    }
}
