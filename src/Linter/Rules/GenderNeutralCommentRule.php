<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\FileGate;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

use function preg_match;

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
        // Comments are part of the source text, so one scan of the whole
        // file with the same pattern soundly skips the per-comment scans.
        $this->gate ??= new FileGate(pattern: self::PATTERN);
        if (!$this->gate->passes($context->file)) {
            return;
        }

        foreach ($context->file->getTrivia() as $trivia) {
            if (preg_match(self::PATTERN, $context->file->getText($trivia->span)) !== 1) {
                continue;
            }

            $context->report(Issue::new('Unnecessarily gendered language in a comment.', $trivia->span));
        }
    }
}
