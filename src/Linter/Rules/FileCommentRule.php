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
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\Trivia;
use Mago\Sdk\Syntax\TriviaKind;

use function preg_match;
use function substr;

/**
 * Checks that a procedural file starts with a docblock tagged `@file`.
 *
 * Ports the part of Drupal.Commenting.FileComment that matters outside
 * core. A procedural file has no class to document, so it must have its own
 * file comment. The rest of that sniff decides whether a file with a class
 * must have a separate file comment next to its class comment. That depends
 * on how many declarations the file has, and is not ported.
 */
final class FileCommentRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/file-comment',
            name: 'File comment',
            description: 'Checks that a procedural file starts with a docblock tagged @file.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        if (!DrupalFile::fromSource($context->file)->isProcedural()) {
            return;
        }

        $first = $this->firstComment($context);
        if ($first === null) {
            $context->report(Issue::new('The file does not start with a docblock.', new Span(0, 0)));

            return;
        }

        if ($first->kind !== TriviaKind::DocBlockComment) {
            $issue = Issue::new('The file docblock must start with "/**".', $first->span);
            $fix = CommentDocblock::fileEdit($context->file, $first);
            $context->report($fix === null ? $issue : $issue->withEdit($fix));

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
        $context->report(
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
        $context->report($moved === null ? $issue : $issue->withEdit($moved));
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

        $context->report(Issue::new(
            'Put a blank line after the file docblock.',
            new Span($docblock->end - 2, $docblock->end),
        )->withEdit($edit));
    }

    /**
     * The file's first comment when only the opening tag and whitespace come
     * before it, past any directive such as `// phpcs:ignoreFile`. phpcs
     * reads those as instructions, not comments, so Coder skips them too.
     */
    private function firstComment(LintContext $context): ?Trivia
    {
        $start = 0;
        $prefix = '';
        foreach ($context->file->getTrivia() as $trivia) {
            $prefix .= substr($context->file->contents, $start, $trivia->span->start - $start);
            if (preg_match('/^<\?php\s*$/', $prefix) !== 1) {
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
