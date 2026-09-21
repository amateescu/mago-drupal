<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\DrupalFile;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
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

        $first = $context->file->getTrivia()[0] ?? null;
        if ($first === null || !$this->atFileStart($context->file->contents, $first->span)) {
            $context->report(Issue::new('The file does not start with a docblock.', new Span(0, 0)));

            return;
        }

        if ($first->kind !== TriviaKind::DocBlockComment) {
            $context->report(Issue::new('The file docblock must start with "/**".', $first->span));

            return;
        }

        foreach (Docblocks::tags($context->file, $first->span) as $tag) {
            if ($tag->name === 'file') {
                return;
            }
        }

        $context->report(Issue::new('The file docblock must have an @file tag.', $first->span));
    }

    /**
     * Whether only the opening `<?php` tag and whitespace come before a
     * comment. That makes it the file's own comment, not a comment on the
     * code after it.
     */
    private function atFileStart(string $contents, Span $span): bool
    {
        return preg_match('/^<\?php\s*$/', substr($contents, offset: 0, length: $span->start)) === 1;
    }
}
