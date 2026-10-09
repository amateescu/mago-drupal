<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\OuterDocblocks;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;

use function count;
use function mb_substr;
use function preg_match;
use function rtrim;
use function strlen;
use function strtolower;

/**
 * Checks that a docblock's long description does not end with a bare
 * letter.
 *
 * Ports `LongFullStop` of Drupal.Commenting.DocComment, which core's
 * `phpcs.xml.dist` turns off. Only a last letter is reported, as Coder does.
 * A long description can end with a colon before a list, a quoted token, or
 * a digit, and a report on those gives dozens of false positives on Drupal
 * core. The fix adds a full stop, as phpcbf does.
 */
final class LongDescriptionPunctuationRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        // Function and Method are targets for `OuterDocblocks::of()`. Their
        // own dispatches do nothing.
        return new RuleDefinition(
            code: 'drupal/long-description-punctuation',
            name: 'Long description punctuation',
            description: "Checks that a docblock's long description does not end with a letter.",
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program, NodeKind::Function, NodeKind::Method],
        );
    }

    public function lint(LintContext $context): void
    {
        if ($context->node->kind !== NodeKind::Program) {
            return;
        }

        foreach (OuterDocblocks::of($context->file) as $span) {
            if (OuterDocblocks::isGroup($context->file, $span)) {
                continue;
            }

            [, $description] = Docblocks::paragraphs($context->file, $span);
            if ($description === []) {
                continue;
            }

            // The text is trimmed first. Otherwise a stray trailing space, or
            // a "\r" that the line splitter keeps under CRLF, hides the last
            // character.
            $last = $description[count($description) - 1];
            $trimmed = rtrim($last->text);
            $lastChar = mb_substr($trimmed, -1);
            if (strtolower($trimmed) === '{@inheritdoc}' || preg_match('/[a-zA-Z]/', $lastChar) !== 1) {
                continue;
            }

            $end = $last->offset + strlen($trimmed);
            $context->report(Issue::new(
                'The long description must end with terminal punctuation.',
                new Span($end - strlen($lastChar), $end),
            )->withEdit(TextEdit::insert($end, '.')));
        }
    }
}
