<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Docblocks;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\TriviaKind;

use function rtrim;
use function str_ends_with;
use function strlen;
use function strrpos;
use function substr;

/**
 * Reports a `//` comment sharing a line with the statement before it.
 *
 * Ports Drupal.Commenting.PostStatementComment. A comment describing a
 * statement belongs on its own line above it, not trailing after it, except
 * right after a closing brace, which the sniff this ports treats as the end
 * of a block rather than a statement to comment on. A trailing directive
 * (`$x = foo(); // phpcs:ignore Some.Sniff`) is exempt: it has to share the
 * statement's line to work at all, and phpcs tokenizes its own annotations
 * as non-comment tokens, so the ported sniff never saw them either.
 */
final class PostStatementCommentRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/post-statement-comment',
            name: 'Post-statement comment',
            description: 'Reports a `//` comment sharing a line with the statement before it.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $contents = $context->file->contents;
        foreach ($context->file->getTrivia() as $trivia) {
            if ($trivia->kind !== TriviaKind::SingleLineComment || Docblocks::isDirective($context->file, $trivia)) {
                continue;
            }

            // Searching within the prefix via a negative offset avoids
            // copying it: strrpos() skips the trailing (length - start)
            // bytes and searches only what is left, which is the same
            // range substr($contents, 0, $start) would have copied out.
            $lineStart = strrpos($contents, needle: "\n", offset: $trivia->span->start - strlen($contents));
            $lineStart = $lineStart === false ? 0 : $lineStart + 1;

            $before = rtrim(substr($contents, $lineStart, $trivia->span->start - $lineStart));
            if ($before === '' || str_ends_with($before, '}')) {
                continue;
            }

            $context->report(Issue::new(
                'A comment may not appear after a statement on the same line.',
                $trivia->span,
            )->withHelp('Move the comment to its own line above the statement.'));
        }
    }
}
