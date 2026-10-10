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

use function in_array;
use function stripos;
use function strlen;
use function strspn;
use function strtolower;
use function substr;

/**
 * Reports the legacy PHPUnit `@expectedException*` docblock tags.
 *
 * Ports DrupalPractice.Commenting.ExpectedException. PHPUnit no longer has
 * these tags. Use `expectException()` and the related methods instead.
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
        // Almost no file has these legacy tags. One scan of the raw source
        // skips the docblock parsing for the rest.
        if (stripos($context->file->contents, needle: '@expectedexception') === false) {
            return;
        }

        foreach ($context->file->getTrivia() as $trivia) {
            if ($trivia->kind !== TriviaKind::DocBlockComment) {
                continue;
            }

            // phpcs finds a tag at the start of every docblock line, at any
            // indent. Docblocks::tags() puts a tag indented under another tag
            // into that tag's text, so this scans the lines instead.
            foreach (Docblocks::lines($context->file, $trivia->span) as $line) {
                $at = strspn($line->text, characters: " \t");
                if (($line->text[$at] ?? '') !== '@') {
                    continue;
                }

                $name = substr(
                    $line->text,
                    offset: $at + 1,
                    length: strspn($line->text, Docblocks::TAG_NAME_CHARACTERS, offset: $at + 1),
                );
                if (!in_array(strtolower($name), self::TAGS, strict: true)) {
                    continue;
                }

                $start = $line->offset + $at;
                $context->report(Issue::new(
                    "Do not use @{$name}. Use \$this->expectException() and the related methods instead.",
                    new Span($start, $start + 1 + strlen($name)),
                )->withLink('https://thephp.cc/news/2016/02/questioning-phpunit-best-practices'));
            }
        }
    }
}
