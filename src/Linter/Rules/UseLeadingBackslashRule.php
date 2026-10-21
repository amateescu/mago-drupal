<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Text\SourceText;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;

use function preg_match;
use function strlen;

/**
 * Reports an import whose first name starts with a backslash.
 *
 * Ports SlevomatCodingStandard.Namespaces.UseDoesNotStartWithBackslash, which
 * Coder 9 runs. A `use` statement always names a class, function or constant
 * from the root, so the backslash doesn't add anything. Like the sniff, the
 * rule checks only the first name of a statement: the name after `use`,
 * `use function` or `use const`, or the prefix of a group.
 */
final class UseLeadingBackslashRule implements Rule
{
    /**
     * The `use` keyword and the type before a first name that starts with a
     * backslash, up to and including that backslash.
     */
    private const LEADING_BACKSLASH =
        '/\\Guse' . SourceText::GAP . '(?:(?:function|const)(?![\\w\\x80-\\xff])' . SourceText::GAP . ')?\\\\/i';

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/use-leading-backslash',
            name: 'Use statement leading backslash',
            description: 'Reports a use statement whose first name starts with a backslash.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Use],
        );
    }

    public function lint(LintContext $context): void
    {
        // The first name of the statement comes after the `use` keyword and
        // an optional `function` or `const` type, with only comments and
        // whitespace around them. Reading the text costs less than walking
        // the statement's nodes.
        $found = [];
        if (
            preg_match(self::LEADING_BACKSLASH, $context->file->contents, $found, offset: $context->node->span->start)
            !== 1
        ) {
            return;
        }

        $start = $context->node->span->start + strlen($found[0]) - 1;
        $span = new Span($start, $start + 1);
        $context->report(Issue::new(
            'Do not start an imported name with a backslash.',
            $span,
        )->withEdit(TextEdit::delete($span)));
    }
}
