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

use function in_array;
use function stripos;

/**
 * Reports the legacy PHPUnit `@expectedException*` docblock tags.
 *
 * Ports DrupalPractice.Commenting.ExpectedException. PHPUnit dropped these in
 * favor of `expectException()` and its siblings.
 *
 * @see https://thephp.cc/news/2016/02/questioning-phpunit-best-practices
 */
final class ExpectedExceptionTagRule implements Rule
{
    private const TAGS = [
        'expectedexception',
        'expectedexceptioncode',
        'expectedexceptionmessage',
        'expectedexceptionmessageregexp',
    ];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/expected-exception-tag',
            name: 'Legacy expectedException tag',
            description: 'Reports the legacy PHPUnit @expectedException* docblock tags.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        // Almost no file mentions these legacy tags, so one scan of the raw
        // source skips the docblock parsing entirely for the rest.
        if (stripos($context->file->contents, needle: '@expectedexception') === false) {
            return;
        }

        foreach ($context->file->getTrivia() as $trivia) {
            if ($trivia->kind !== TriviaKind::DocBlockComment) {
                continue;
            }

            foreach (Docblocks::tags($context->file, $trivia->span) as $tag) {
                if (!in_array($tag->name, self::TAGS, strict: true)) {
                    continue;
                }

                $context->report(Issue::new(
                    "@{$tag->name} should not be used, use \$this->expectException() and its siblings instead.",
                    $tag->nameSpan,
                )->withLink('https://thephp.cc/news/2016/02/questioning-phpunit-best-practices'));
            }
        }
    }
}
