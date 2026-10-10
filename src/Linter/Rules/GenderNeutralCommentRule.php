<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\DocblockRows;
use amateescu\MagoDrupal\Internal\FileGate;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\TriviaKind;

use function explode;
use function preg_match;
use function strlen;

use const PREG_OFFSET_CAPTURE;

/**
 * Reports gendered pronouns in comments.
 *
 * Ports Drupal.Commenting.GenderNeutralComment.
 */
final class GenderNeutralCommentRule implements Rule
{
    private const PATTERN = '/(^|\W)(he|her|hers|him|his|she)($|\W)/i';

    private ?FileGate $gate = null;

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/gender-neutral-comment',
            name: 'Gender neutral comment',
            description: 'Reports gendered pronouns in comments.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        // Comments are part of the source text. One scan of the whole file
        // with the same pattern is enough to skip the per-comment scans.
        $this->gate ??= new FileGate(pattern: self::PATTERN);
        if (!$this->gate->passes($context->file)) {
            return;
        }

        foreach ($context->file->getTrivia() as $trivia) {
            // A comment with no match has no line with one, so one scan of
            // the whole comment skips most of them.
            if (preg_match(self::PATTERN, $context->file->getText($trivia->span)) !== 1) {
                continue;
            }

            // Coder checks each line of a comment on its own and reports
            // each line that matches. On a docblock line it skips the tag
            // name and checks the text after it.
            if ($trivia->kind === TriviaKind::DocBlockComment) {
                foreach (DocblockRows::of($context->file, $trivia->span) as $row) {
                    $value = $row->value();
                    $this->check($context, $value, $row->textEnd - strlen($value));
                }

                continue;
            }

            $offset = $trivia->span->start;
            foreach (explode("\n", $context->file->getText($trivia->span)) as $line) {
                $this->check($context, $line, $offset);
                $offset += strlen($line) + 1;
            }
        }
    }

    /**
     * Reports the first pronoun in one line of text that starts at $offset.
     */
    private function check(LintContext $context, string $text, int $offset): void
    {
        $matches = [];
        if (preg_match(self::PATTERN, $text, $matches, flags: PREG_OFFSET_CAPTURE) !== 1) {
            return;
        }

        // Group 2 is the pronoun, as a value and byte offset pair. The stub
        // for preg_match() does not model the offset-capture shape.
        // @mago-expect analysis:docblock-type-mismatch
        /** @var array{string, int} $pronoun */
        $pronoun = $matches[2];
        $start = $offset + $pronoun[1];

        $context->report(Issue::new(
            'The comment uses a gendered pronoun.',
            new Span($start, $start + strlen($pronoun[0])),
        ));
    }
}
