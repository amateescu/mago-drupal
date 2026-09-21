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

use function stripos;

/**
 * Reports `@author` tags in docblocks.
 *
 * Ports DrupalPractice.Commenting.AuthorTag. Many people edit a file over
 * time, so the tag goes out of date. Git already records who wrote what.
 */
final class AuthorTagRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/author-tag',
            name: 'Author tag',
            description: 'Reports @author tags in docblocks.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        // Almost no file has the tag. One scan of the raw source skips the
        // docblock parsing for the rest.
        if (stripos($context->file->contents, needle: '@author') === false) {
            return;
        }

        foreach ($context->file->getTrivia() as $trivia) {
            if ($trivia->kind !== TriviaKind::DocBlockComment) {
                continue;
            }

            foreach (Docblocks::tags($context->file, $trivia->span) as $tag) {
                if ($tag->name !== 'author') {
                    continue;
                }

                $context->report(Issue::new(
                    'Do not use @author tags, because many contributors edit the code over time.',
                    $tag->nameSpan,
                ));
            }
        }
    }
}
