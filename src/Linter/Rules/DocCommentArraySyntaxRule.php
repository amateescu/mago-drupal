<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Docblocks;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\TriviaKind;

use function preg_match;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strpos;

/**
 * Reports `array()` syntax inside a docblock's `@code` example block.
 *
 * Ports Drupal.Commenting.DocCommentLongArraySyntax. `@code` content is
 * example source, not real code Mago parses, so nothing else catches this.
 */
final class DocCommentArraySyntaxRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/doc-comment-array-syntax',
            name: 'Doc comment array syntax',
            description: 'Reports `array()` syntax inside a docblock @code example.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        // Most files have no @code example at all, so one scan of the raw
        // source skips the docblock parsing entirely for them.
        if (!str_contains($context->file->contents, needle: '@code')) {
            return;
        }

        foreach ($context->file->getTrivia() as $trivia) {
            if ($trivia->kind !== TriviaKind::DocBlockComment) {
                continue;
            }

            $inCode = false;
            foreach (Docblocks::lines($context->file, $trivia->span) as $line) {
                if (str_starts_with($line->text, '@endcode')) {
                    $inCode = false;
                    continue;
                }

                if (str_starts_with($line->text, '@code')) {
                    $inCode = true;
                    continue;
                }

                if ($inCode) {
                    $this->checkLine($context, $line->text, $line->offset);
                }
            }
        }
    }

    private function checkLine(LintContext $context, string $text, int $offset): void
    {
        $matches = [];
        if (preg_match('/\barray\s*\(/', $text, $matches) !== 1) {
            return;
        }

        $position = (int) strpos($text, $matches[0]);
        $context->report(Issue::new(
            'Long array syntax must not be used in doc comment @code examples.',
            new Span($offset + $position, $offset + $position + strlen($matches[0])),
        ));
    }
}
