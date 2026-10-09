<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

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
 * Reports a PHP open tag that the next close tag follows with nothing between.
 *
 * Ports Generic.CodeAnalysis.EmptyPHPStatement.EmptyPHPOpenCloseTagsDetected.
 * The stray `;` code of that sniff stays with Mago's `no-noop`.
 */
final class EmptyPhpTagsRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/empty-php-tags',
            name: 'Empty PHP tags',
            description: 'Reports an open tag that a close tag follows with only whitespace between.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::OpeningTag, NodeKind::EchoTag],
        );
    }

    public function lint(LintContext $context): void
    {
        $start = $context->node->span->start;
        $isEcho = $context->node->kind === NodeKind::EchoTag;
        $pattern = $isEcho ? '/\G<\?=\s*\?>/' : '/\G<\?php\s+\?>(?:\r\n|\r|\n)?/i';
        $match = [];
        if (preg_match($pattern, $context->file->contents, $match, offset: $start) !== 1) {
            return;
        }

        $issue = Issue::new('Remove the PHP tags that hold no code.', new Span($start, $start + 2));

        // PHP skips one line break after a close tag, so the fix removes it
        // with the pair and the output stays the same. An empty echo tag is a
        // parse error, and dropping it would turn a broken file into a
        // working one, so it gets no fix.
        $context->report(
            $isEcho ? $issue : $issue->withEdit(TextEdit::delete(new Span($start, $start + strlen($match[0])))),
        );
    }
}
