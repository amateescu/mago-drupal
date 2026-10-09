<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\SourceText;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\Safety;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;

use function substr;
use function trim;

/**
 * Reports a destructuring written as `list(...)`.
 *
 * Ports SlevomatCodingStandard.PHP.ShortList. The fix writes `[...]`. A
 * comment between `list` and the opening parenthesis would be lost, so
 * that fix is potentially unsafe. An empty `list()` has no fix, since PHP
 * rejects both spellings.
 */
final class ShortListRule implements Rule
{
    private const KEYWORD_LENGTH = 4;

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/short-list',
            name: 'Short list',
            description: 'Reports a list(...) destructuring that can be written as [...].',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::List],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $span = $context->node->span;
        $issue = Issue::new(
            'Use [...] instead of list(...).',
            new Span($span->start, $span->start + self::KEYWORD_LENGTH),
        );

        // The gap between the keyword and the parenthesis holds blank space
        // and perhaps comments.
        $after = $span->start + self::KEYWORD_LENGTH;
        $open = SourceText::skipBlank($file->contents, $after);
        if (
            ($file->contents[$open] ?? '') !== '('
            || $open >= $span->end
            || $file->getChildren($context->node) === []
        ) {
            $context->report($issue);

            return;
        }

        $gap = substr($file->contents, $after, $open - $after);
        $safety = trim($gap) === '' ? Safety::Safe : Safety::PotentiallyUnsafe;
        $context->report(
            $issue
                ->withEdit(TextEdit::replace(new Span($span->start, $open + 1), '[')->withSafety($safety))
                ->withEdit(TextEdit::replace(new Span($span->end - 1, $span->end), ']')->withSafety($safety)),
        );
    }
}
