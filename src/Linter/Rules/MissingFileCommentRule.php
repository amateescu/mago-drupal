<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\FileDocblock;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;

/**
 * Checks that a PHP file that is not a class file starts with a comment.
 *
 * Ports `Missing` of Drupal.Commenting.FileComment, with the sniff's skip
 * of a file that holds a class. `drupal/file-comment` checks that the
 * comment is a docblock with an `@file` tag.
 */
final class MissingFileCommentRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/missing-file-comment',
            name: 'Missing file comment',
            description: "Reports a PHP file that isn't a class file and has no comment at its start.",
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program, ...FileDocblock::DECLARATIONS],
        );
    }

    public function lint(LintContext $context): void
    {
        // The declarations are targets only so that the class file check can
        // read them from the target-node list.
        if ($context->node->kind !== NodeKind::Program) {
            return;
        }

        $file = $context->file;
        if (
            FileDocblock::firstComment($file) === null
            && FileDocblock::hasOpenTag($file)
            && !FileDocblock::isClassFile($file)
        ) {
            $context->report(Issue::new('The file does not start with a docblock.', new Span(0, 0)));
        }
    }
}
