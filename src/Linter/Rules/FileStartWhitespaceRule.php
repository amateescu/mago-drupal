<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\Safety;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;

use function count;
use function preg_match;
use function substr;

/**
 * Reports whitespace before the first open tag of a file.
 *
 * Ports Squiz.WhiteSpace.SuperfluousWhitespace.StartFile.
 */
final class FileStartWhitespaceRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/file-start-whitespace',
            name: 'File start whitespace',
            description: 'Reports whitespace before the first PHP open tag of a file.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $statements = $context->file->getChildren($context->node);
        if (count($statements) < 2) {
            return;
        }

        $text = $context->file->getChildren($statements[0])[0] ?? null;
        $tag = $context->file->getChildren($statements[1])[0] ?? null;
        if (
            $text === null
            || $tag === null
            || $text->kind !== NodeKind::Inline
            || $tag->kind !== NodeKind::OpeningTag
        ) {
            return;
        }

        // Coder's pattern: only ASCII whitespace and Unicode separators, and
        // valid UTF-8. A BOM, a zero-width space or a shebang line fail it.
        $before = substr($context->file->contents, offset: 0, length: $tag->span->start);
        if ($tag->span->start !== $text->span->end || preg_match('/^[\pZ\s]+$/u', $before) !== 1) {
            return;
        }

        // The text can show up on the page, as a blank first line of a
        // template does. Removing it can change the output.
        $context->report(Issue::new(
            'Additional whitespace found at start of file.',
            $tag->span,
        )->withEdit(TextEdit::delete(new Span(0, $tag->span->start))->withSafety(Safety::PotentiallyUnsafe)));
    }
}
