<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\CoreFiles;
use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\DocblockSummary;
use amateescu\MagoDrupal\Internal\OuterDocblocks;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

use function preg_match;

/**
 * Checks that a docblock has a short description.
 *
 * Ports `MissingShort` of Drupal.Commenting.DocComment. A `phpcs:` line
 * inside the docblock is not part of a description. Coder reads it the same
 * way below the summary, but above it Coder doesn't find a summary. The summary
 * of a file docblock is the text after its `@file` tag.
 */
final class MissingSummaryRule implements Rule
{
    /**
     * The directories where core's `phpcs.xml.dist` runs `MissingShort`.
     */
    private const CORE_SUMMARY_DIRECTORIES = '#/(?:KernelTests|Unit|FunctionalJavascript)/#';

    public function getDefinition(): RuleDefinition
    {
        // Function and Method are targets for `OuterDocblocks::of()`. Their
        // own dispatches don't do anything.
        return new RuleDefinition(
            code: 'drupal/missing-summary',
            name: 'Missing summary',
            description: 'Reports a docblock with tags and no short description.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program, NodeKind::Function, NodeKind::Method],
        );
    }

    public function lint(LintContext $context): void
    {
        if (
            $context->node->kind !== NodeKind::Program
            || preg_match(self::CORE_SUMMARY_DIRECTORIES, $context->file->path) !== 1
            && CoreFiles::contains($context->file->path)
        ) {
            return;
        }

        foreach (OuterDocblocks::of($context->file) as $span) {
            // Mago's no-empty-comment reports an empty docblock, and its safe
            // fix deletes it.
            $tags = Docblocks::tags($context->file, $span);
            if (
                $tags === []
                || OuterDocblocks::isGroup($context->file, $span)
                || Docblocks::paragraphs($context->file, $span)[0] !== []
            ) {
                continue;
            }

            // Coder reads the first tag, or the one after a leading `@file`.
            // The text after `@file` is a file docblock's summary, so a
            // docblock that holds `@file` and nothing else doesn't have one, as
            // Coder reports. `@covers` doesn't need a summary, as PHPUnit test
            // methods often don't have one, and a bare `@inheritdoc` gets the
            // braces report of `drupal/doc-comment` instead. Coder doesn't read
            // any other tag, so `@covers` followed by `@group` is fine.
            $tag = DocblockSummary::leadTag($context->file, $tags);
            if ($tag === null) {
                if ($tags[0]->content() === '') {
                    $context->report(Issue::new('The docblock doesn\'t have a short description.', $span));
                }

                continue;
            }

            if ($tag->name === 'inheritdoc' || $context->file->getText($tag->nameSpan) === '@covers') {
                continue;
            }

            $context->report(Issue::new('The docblock doesn\'t have a short description.', $span));
        }
    }
}
