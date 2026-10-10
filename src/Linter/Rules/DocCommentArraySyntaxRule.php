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
use function strcspn;
use function strlen;
use function strpos;
use function strspn;
use function substr;

/**
 * Reports `array()` syntax inside a docblock's `@code` example block.
 *
 * Ports Drupal.Commenting.DocCommentLongArraySyntax. `@code` content is
 * example source, not real code that Mago parses. Nothing else reports this.
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
        // Most files have no @code example at all. One scan of the raw source
        // skips the docblock parsing for them. A report needs both an example
        // and the long syntax somewhere in the file. Almost no file has the
        // long syntax.
        if (
            !str_contains($context->file->contents, needle: '@code')
            || preg_match('/\barray\s*\(/', $context->file->contents) !== 1
        ) {
            return;
        }

        foreach ($context->file->getTrivia() as $trivia) {
            if ($trivia->kind !== TriviaKind::DocBlockComment) {
                continue;
            }

            $inCode = false;
            foreach (Docblocks::lines($context->file, $trivia->span) as $line) {
                // Coder's tokenizer reads a tag only at the start of a line,
                // after any indent, up to the first space or tab.
                $indent = strspn($line->text, characters: " \t");
                $tag = substr($line->text, $indent, strcspn($line->text, characters: " \t", offset: $indent));
                if ($tag === '@endcode') {
                    $inCode = false;
                    continue;
                }

                if ($tag === '@code') {
                    // The text after the tag on its line is part of the example.
                    $inCode = true;
                    $start = $indent + strlen($tag);
                    $this->checkLine($context, substr($line->text, $start), $line->offset + $start);
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
            'Do not use the long array syntax in a docblock @code example.',
            new Span($offset + $position, $offset + $position + strlen($matches[0])),
        ));
    }
}
