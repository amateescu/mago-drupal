<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\CommentDocblock;
use amateescu\MagoDrupal\Internal\DocblockGap;
use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\DrupalFile;
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

use function count;
use function preg_match;
use function strspn;
use function substr;

/**
 * Checks that a procedural file starts with a docblock tagged `@file`, and
 * that a file with a namespace and one class, interface, trait or enum does
 * not start with a comment.
 *
 * Ports Drupal.Commenting.FileComment, with the sniff's skip of the
 * procedural checks for a file that holds a class.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class FileCommentRule implements Rule
{
    /**
     * The declarations that Coder counts as a class.
     */
    private const CLASS_LIKES = [NodeKind::Class_, NodeKind::Interface, NodeKind::Trait, NodeKind::Enum];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/file-comment',
            name: 'File comment',
            description: 'Checks that a procedural file starts with a docblock tagged @file, and that a namespaced class file does not start with a comment.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        // Coder reports a comment at the start of a namespaced class file
        // with any extension, so this check comes before the one for a
        // procedural file. The tree scan runs only for a file that starts
        // with a comment.
        $first = $this->firstComment($context);
        if ($first !== null && self::isNamespacedClassFile($context->file)) {
            $this->reportNamespacedFileComment($context, $first);

            return;
        }

        if (!DrupalFile::fromSource($context->file)->isProcedural()) {
            return;
        }

        if ($first === null) {
            $this->report($context, Issue::new('The file does not start with a docblock.', new Span(0, 0)));

            return;
        }

        if ($first->kind !== TriviaKind::DocBlockComment) {
            $issue = Issue::new('The file docblock must start with "/**".', $first->span);
            $fix = CommentDocblock::fileEdit($context->file, $first);
            $this->report($context, $fix === null ? $issue : $issue->withEdit($fix));

            return;
        }

        // Tag names are case-sensitive in Coder, so `@FILE` is not a file tag.
        [$tag, $variant] = Docblocks::fileTag($context->file, $first->span);
        if ($tag !== null) {
            $this->checkTagPosition($context, $first->span, $tag);

            return;
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
    }

    /**
     * Reports an `@file` tag that is not on the line right below the
     * docblock's opener, and checks the blank line below the docblock when
     * it is.
     *
     * A potentially unsafe fix moves a tag that stands alone on its line.
     */
    private function checkTagPosition(LintContext $context, Span $docblock, Span $tag): void
    {
        $contents = $context->file->contents;
        if (FileTags::onSecondLine($contents, $docblock, $tag)) {
            $this->checkBlankLineAfter($context, $docblock);

            return;
        }

        $issue = Issue::new('The second line in the file docblock must be "@file".', FileTags::reportSpan(
            $contents,
            $docblock,
        ));
        $moved = FileTags::move($contents, $docblock, $tag);
        $this->report($context, $moved === null ? $issue : $issue->withEdit($moved));
    }

    /**
     * Reports a file docblock with no blank line below it. `mago format`
     * already turns several blank lines into one, so only a missing one is
     * left to report. Coder skips a docblock right above a `?>`.
     */
    private function checkBlankLineAfter(LintContext $context, Span $docblock): void
    {
        $edit = DocblockGap::blankLine($context->file, $docblock);
        if ($edit === null || preg_match('/\G\s*\?>/', $context->file->contents, offset: $docblock->end) === 1) {
            return;
        }

        $issue = Issue::new('Put a blank line after the file docblock.', new Span($docblock->end - 2, $docblock->end));
        $this->report($context, $issue->withEdit($edit));
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
     * Reports the issue unless Coder skips the file comment of the file.
     */
    private function report(LintContext $context, Issue $issue): void
    {
        // The skip is checked only for a file that has an issue, because it
        // scans the whole tree.
        if (!self::isClassFile($context->file)) {
            $context->report($issue);
        }
    }

    /**
     * Whether Coder skips the file comment of the file. It skips a file that
     * has a class, interface, trait or enum and a namespace. It also skips a
     * file that has exactly one of them, no function or method outside it,
     * and no `@file` tag in any docblock.
     */
    private static function isClassFile(SourceFile $file): bool
    {
        $declarations = self::classLikes($file);
        if ($declarations === []) {
            return false;
        }

        if ($file->getNodes(NodeKind::Namespace) !== []) {
            return true;
        }

        if (count($declarations) > 1) {
            return false;
        }

        // Coder counts a function, or a method of an anonymous class, outside
        // the class. A closure or an arrow function does not count.
        $class = $declarations[0]->span;
        foreach ([...$file->getNodes(NodeKind::Function), ...$file->getNodes(NodeKind::Method)] as $function) {
            if (!$class->contains($function->span)) {
                return false;
            }
        }

        foreach ($file->getTrivia() as $trivia) {
            if ($trivia->kind === TriviaKind::DocBlockComment && Docblocks::fileTag($file, $trivia->span)[0] !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the file has a namespace and exactly one class, interface,
     * trait or enum, which Coder wants without a file comment.
     */
    private static function isNamespacedClassFile(SourceFile $file): bool
    {
        return $file->getNodes(NodeKind::Namespace) !== [] && count(self::classLikes($file)) === 1;
    }

    /**
     * The class, interface, trait and enum declarations of the file.
     *
     * @return list<Node>
     */
    private static function classLikes(SourceFile $file): array
    {
        $declarations = [];
        foreach (self::CLASS_LIKES as $kind) {
            $declarations = [...$declarations, ...$file->getNodes($kind)];
        }

        return $declarations;
    }

    /**
     * The file's first comment when only the opening tag and whitespace come
     * before it, past any directive such as `// phpcs:ignoreFile`. A
     * directive is a tool instruction, not the file comment. Coder does not
     * look past one.
     * A UTF-8 byte order mark may come before the opening tag, and
     * `drupal/byte-order-mark` reports it.
     */
    private function firstComment(LintContext $context): ?Trivia
    {
        $start = 0;
        $prefix = '';
        foreach ($context->file->getTrivia() as $trivia) {
            $prefix .= substr($context->file->contents, $start, $trivia->span->start - $start);
            if (preg_match('/^(?:\xEF\xBB\xBF)?<\?php\s*$/', $prefix) !== 1) {
                return null;
            }

            if (!Docblocks::isDirective($context->file, $trivia)) {
                return $trivia;
            }

            $start = $trivia->span->end;
        }

        return null;
    }
}
