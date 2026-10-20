<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\FunctionTags;
use amateescu\MagoDrupal\Internal\OuterDocblocks;
use amateescu\MagoDrupal\Internal\SummarySentences;
use amateescu\MagoDrupal\Internal\TagSentences;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

/**
 * Checks that the summary, the long description and the `@param` and
 * `@throws` descriptions of a docblock read as sentences.
 *
 * Ports the wording codes of Drupal.Commenting.DocComment, on every
 * docblock outside a function body, and of
 * Drupal.Commenting.FunctionComment, on the tags of a function or method.
 * `SummarySentences` and `TagSentences` hold the checks.
 */
final class DocSentenceRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/doc-sentence',
            name: 'Doc sentence',
            description: 'Reports a docblock summary, @param or @throws description with a wrong first letter or ending, a long description that starts with a lower-case letter, and a summary on more than one line.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program, NodeKind::Function, NodeKind::Method],
        );
    }

    public function lint(LintContext $context): void
    {
        if ($context->node->kind === NodeKind::Program) {
            foreach (OuterDocblocks::of($context->file) as $span) {
                if (OuterDocblocks::isGroup($context->file, $span)) {
                    continue;
                }

                SummarySentences::check($context, $span);
            }

            return;
        }

        $tags = FunctionTags::of($context);
        if ($tags === null) {
            return;
        }

        // Coder checks the wording only when the tag has a type.
        foreach ($tags->params() as [, $param]) {
            if ($param === null || $param->type === '' || $param->description === '') {
                continue;
            }

            TagSentences::checkParam($context, $tags->tags, $param);
        }

        foreach ($tags->named('throws') as $tag) {
            TagSentences::checkThrows($context, $tag);
        }
    }
}
