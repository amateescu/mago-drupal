<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Calls;
use amateescu\MagoDrupal\Internal\DocumentStrings;
use amateescu\MagoDrupal\Internal\EscapedQuotes;
use amateescu\MagoDrupal\Internal\TranslatableCall;
use amateescu\MagoDrupal\Internal\Values;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;

use function trim;

/**
 * Checks the strings that a call passes to t() and the other translation entry
 * points.
 *
 * Ports Drupal.Semantics.FunctionT. A translatable string must be one
 * literal, because the extractor reads the source and does not run it. This
 * rule reports a string built with `.`, an empty or padded one, a call with
 * no string, and a quote escaped with a backslash.
 * `drupal/translatable-literal` reports a string that is not a literal at
 * all, and a string joined after the call. A nowdoc counts as a literal, as
 * in Coder 9; a heredoc does not.
 */
final class TranslatableStringRule implements Rule
{
    private const HELP = 'The string extractor reads the source code, so it sees only whole literals.';

    private const PADDED = 'Do not start or end a translatable string with whitespace.';

    private const PADDED_HELP = 'Use placeholders for the variable parts instead of padding the literal.';

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/translatable-string',
            name: 'Translatable string',
            description: 'Reports a translatable string built with ".", an empty or padded one, a call with no string, and an escaped quote.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [...Calls::CALL_KINDS, NodeKind::Instantiation],
        );
    }

    public function lint(LintContext $context): void
    {
        $call = TranslatableCall::of($context);
        if ($call === null) {
            return;
        }

        // A call with only named or only unpacked arguments is not empty. It
        // has no readable positional argument, so the rule does not check it.
        if ($call->invocation->isEmpty()) {
            $context->report(Issue::new('Do not call ' . $call->callee . ' without arguments.', $context->node->span));

            return;
        }

        foreach ($call->positions as $position) {
            $message = $call->invocation->argument($position);
            if ($message === null) {
                continue;
            }

            $this->check($context, $message, $call->callee);

            // Coder reads the quotes of the first argument of t() and the two
            // markup classes only, not the strings of formatPlural().
            if ($call->name !== 'formatplural') {
                EscapedQuotes::check($context, Values::leftmost($context->file, $message));
            }
        }
    }

    /**
     * Checks one translatable string argument.
     */
    private function check(LintContext $context, Node $message, string $callee): void
    {
        $nowdoc = $message->kind === NodeKind::CompositeString
            ? DocumentStrings::nowdoc($context->file, $message)
            : null;
        if ($nowdoc !== null) {
            if ($nowdoc === '') {
                $context->report(Issue::new('Do not pass an empty string to ' . $callee . '.', $message->span));
            }

            return;
        }

        if ($message->kind !== NodeKind::LiteralString) {
            $this->checkConcatenation($context, $message);

            return;
        }

        $value = Values::literalString($context->file, $message);
        if ($value === null) {
            return;
        }

        if ($value === '') {
            $context->report(Issue::new('Do not pass an empty string to ' . $callee . '.', $message->span));

            return;
        }

        if ($value !== trim($value)) {
            $context->report(Issue::new(self::PADDED, $message->span)->withHelp(self::PADDED_HELP));
        }
    }

    /**
     * Reports a string built with `.`. Any other argument that is not a
     * plain literal is `drupal/translatable-literal`'s.
     */
    private function checkConcatenation(LintContext $context, Node $message): void
    {
        if (!Values::concatenates($context->file, $message)) {
            return;
        }

        $context->report(Issue::new(
            'Do not concatenate a translatable string. Use placeholders instead.',
            $message->span,
        )->withHelp(self::HELP));

        // Coder also checks the padding of the first token, so a padded
        // leftmost literal gets its own report. The later parts are not
        // checked.
        $first = Values::leftmost($context->file, $message);
        $value = Values::literalString($context->file, $first);
        if ($value !== null && $value !== trim($value)) {
            $context->report(Issue::new(self::PADDED, $first->span)->withHelp(self::PADDED_HELP));
        }
    }
}
