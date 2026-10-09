<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\CommentDocblock;
use amateescu\MagoDrupal\Internal\DocblockGap;
use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\DrupalFile;
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
use function strlen;
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

        foreach (Docblocks::tags($context->file, $first->span) as $tag) {
            if ($tag->name !== 'file') {
                continue;
            }

            $this->checkBlankLineAfter($context, $first->span);

            return;
        }

        $issue = Issue::new('The file docblock must have an @file tag.', $first->span);
        $opener = $this->fileTagOffset($context->file->contents, $first->span);
        $context->report(
            $opener === null
                ? $issue
                : $issue->withEdit(TextEdit::insert($opener, LineEnding::of($context->file->contents) . ' * @file')),
        );
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
     * A UTF-8 byte order mark may come before the opening tag.
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

    /**
     * Where `@file` goes, at the end of the opener line, or null when it
     * cannot go on a line of its own: the opener line holds text, the
     * docblock is a group's, or no blank line parts it from the code below.
     * A docblock right on a function is that function's, which `@file`
     * would steal.
     */
    private function fileTagOffset(string $contents, Span $docblock): ?int
    {
        $text = substr($contents, $docblock->start, $docblock->length());
        $opener = [];
        if (
            preg_match('/^\/\*\*[ \t]*(?=\r?\n)/', $text, $opener) !== 1
            || preg_match('/@(?:defgroup|addtogroup)\b/', $text) === 1
            || preg_match('/\G\r?\n[ \t]*\r?\n/', $contents, offset: $docblock->end) !== 1
        ) {
            return null;
        }

        return $docblock->start + strlen($opener[0]);
    }
}
