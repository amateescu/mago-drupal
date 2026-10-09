<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;

use function preg_match;
use function preg_replace;
use function substr;

/**
 * Reports a file that is not valid UTF-8.
 *
 * Ports Drupal.Files.FileEncoding.
 */
final class FileEncodingRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/file-encoding',
            name: 'File encoding',
            description: 'Reports a file whose bytes are not valid UTF-8.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        // The `u` modifier makes PCRE check the whole subject as UTF-8. It
        // returns false on a bad byte, an overlong form, a surrogate or a
        // cut-off sequence, the same set `mb_check_encoding()` rejects.
        if (preg_match('//u', $context->file->contents) === 1) {
            return;
        }

        $start = $this->firstTagOffset($context);
        if ($start === null) {
            return;
        }

        // The message names no bytes. Mago stops the whole run on issue text
        // that is not valid UTF-8.
        $context->report(Issue::new(
            'Save the file as UTF-8. It holds bytes that are not valid UTF-8.',
            new Span($start, $start),
        ));
    }

    /**
     * Where the first open tag or run of inline text starts. A `<?=` tag does
     * not count, so a file with only echo tags is skipped, as in Coder. PHP
     * drops one line break after a close tag, so text that is only that
     * break does not count either.
     */
    private function firstTagOffset(LintContext $context): ?int
    {
        $contents = $context->file->contents;
        foreach ($context->file->getChildren($context->node) as $statement) {
            foreach ($context->file->getChildren($statement) as $child) {
                if ($child->kind === NodeKind::OpeningTag) {
                    return $child->span->start;
                }

                if ($child->kind !== NodeKind::Inline) {
                    continue;
                }

                $text = $context->file->getText($child);
                if ($child->span->start >= 2 && substr($contents, $child->span->start - 2, length: 2) === '?>') {
                    $text = preg_replace('/^(?:\r\n|\r|\n)/', replacement: '', subject: $text) ?? $text;
                }

                if ($text !== '') {
                    return $child->span->start;
                }
            }
        }

        return null;
    }
}
