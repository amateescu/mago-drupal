<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Calls;
use amateescu\MagoDrupal\Internal\Invocation;
use amateescu\MagoDrupal\Internal\Values;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;

use function strrpos;
use function substr;
use function trim;

/**
 * Checks the strings passed to t() and the other translation entry points.
 *
 * Ports Drupal.Semantics.FunctionT. Translatable strings have to be literal and
 * whole, because the extractor reads the source rather than running it.
 */
final class TranslatableStringRule implements Rule
{
    private const HELP = 'The string extractor reads source code, so it only sees whole literals.';

    /**
     * Entry points mapped to the argument positions holding a translatable
     * string. formatPlural() takes the count first, so its strings are second
     * and third.
     */
    private const TRANSLATED_ARGUMENTS = [
        't' => [0],
        'translatablemarkup' => [0],
        'translationwrapper' => [0],
        'formatplural' => [1, 2],
    ];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/translatable-string',
            name: 'Translatable string',
            description: 'Checks that translatable strings are single literals without concatenation or padding.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [...Calls::CALL_KINDS, NodeKind::Instantiation],
        );
    }

    public function lint(LintContext $context): void
    {
        $invocation = Invocation::fromNode($context->file, $context->node);
        if ($invocation === null) {
            return;
        }

        $name = Calls::normalize($invocation->name);
        if ($context->node->kind === NodeKind::Instantiation) {
            // The sniff matches class basenames, so a qualified
            // `new \Foo\TranslatableMarkup()` counts like the imported form.
            $separator = strrpos($name, needle: '\\');
            if ($separator !== false) {
                $name = substr($name, $separator + 1);
            }
        }

        $positions = self::TRANSLATED_ARGUMENTS[$name] ?? null;
        if ($positions === null) {
            return;
        }

        // Named-only and unpacked-only calls are not empty, they just have no
        // readable positional argument, so they fall through and go unchecked.
        if ($invocation->isEmpty()) {
            $context->report(Issue::new('Empty calls to t() are not allowed.', $context->node->span));

            return;
        }

        foreach ($positions as $position) {
            $message = $invocation->argument($position);
            if ($message !== null) {
                $this->check($context, $message);
            }
        }
    }

    /**
     * Checks one translatable string argument.
     */
    private function check(LintContext $context, Node $message): void
    {
        if ($message->kind !== NodeKind::LiteralString) {
            $this->reportNonLiteral($context, $message);

            return;
        }

        $value = Values::literalString($context->file, $message);
        if ($value === null) {
            return;
        }

        if ($value === '') {
            $context->report(Issue::new('Do not pass empty strings to t().', $message->span));

            return;
        }

        if ($value !== trim($value)) {
            $context->report(Issue::new(
                'Translatable strings must not begin or end with whitespace.',
                $message->span,
            )->withHelp('Use placeholders for the variable parts instead of padding the literal.'));
        }
    }

    /**
     * Reports an argument that is not a plain literal.
     *
     * Concatenation and interpolation each get their own message. Both are
     * common, and naming the actual mistake makes the fix obvious.
     */
    private function reportNonLiteral(LintContext $context, Node $message): void
    {
        if (Values::concatenates($context->file, $message)) {
            $context->report(Issue::new(
                'Concatenating translatable strings is not allowed. Use placeholders instead.',
                $message->span,
            )->withHelp(self::HELP));

            return;
        }

        if ($message->kind === NodeKind::InterpolatedString || $message->kind === NodeKind::CompositeString) {
            $context->report(Issue::new(
                'Do not interpolate variables into translatable strings. Use placeholders instead.',
                $message->span,
            )->withHelp(self::HELP));

            return;
        }

        // Variables and constants land here. Passing one is sometimes
        // deliberate, so the message says "where possible".
        $context->report(Issue::new(
            'Only string literals should be passed to t() where possible.',
            $message->span,
        )->withHelp(self::HELP));
    }
}
