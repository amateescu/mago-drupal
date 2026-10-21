<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Syntax\Calls;
use amateescu\MagoDrupal\Internal\Syntax\TranslatableCall;
use amateescu\MagoDrupal\Internal\Syntax\Values;
use amateescu\MagoDrupal\Internal\Text\DocumentStrings;
use amateescu\MagoDrupal\Internal\Text\EscapedQuotes;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function str_contains;
use function strlen;
use function strspn;
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
 *
 * Each of Coder's checks and its exceptions needs a branch of its own.
 *
 * @mago-expect lint:cyclomatic-complexity
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
        // doesn't have a readable positional argument, so the rule does not
        // check it. StringTranslationTrait::formatPlural() needs three
        // arguments, so a formatPlural() call without any is another method.
        if ($call->invocation->isEmpty()) {
            if ($call->name === 'formatplural') {
                return;
            }

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

        if (self::padded($context->file, $message, $value)) {
            $context->report(Issue::new(self::PADDED, $message->span)->withHelp(self::PADDED_HELP));
        }
    }

    /**
     * Whether a literal starts or ends with whitespace, for a literal on one
     * line.
     *
     * phpcs splits a literal that spans lines into one token per line, and
     * Coder checks the padding only when the first token ends in a quote. So
     * Coder never checks a literal that spans lines, and this rule doesn't
     * either. An escape such as `\n` doesn't break the line, so a one-line
     * literal that starts with one is still checked.
     */
    private static function padded(SourceFile $file, Node $literal, string $value): bool
    {
        return $value !== trim($value) && !str_contains($file->getText($literal), "\n");
    }

    /**
     * The `.` right after the first literal, where Coder reports the
     * concatenation, so that a phpcs comment written for Coder covers the
     * same line. The whole argument when something other than whitespace
     * sits between them.
     */
    private static function operator(SourceFile $file, Node $first, Node $message): Span
    {
        $offset = $first->span->end;
        $offset += strspn($file->contents, characters: " \t\r\n", offset: $offset);
        if ($offset >= strlen($file->contents) || $file->contents[$offset] !== '.') {
            return $message->span;
        }

        return new Span($offset, $offset + 1);
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

        $first = Values::leftmost($context->file, $message);
        $value = Values::literalString($context->file, $first);
        $context->report(Issue::new(
            'Do not concatenate a translatable string. Use placeholders instead.',
            $value === null ? $message->span : self::operator($context->file, $first, $message),
        )->withHelp(self::HELP));

        // Coder also checks the padding of the first token, so a padded
        // leftmost literal gets its own report. The later parts are not
        // checked.
        if ($value !== null && self::padded($context->file, $first, $value)) {
            $context->report(Issue::new(self::PADDED, $first->span)->withHelp(self::PADDED_HELP));
        }
    }
}
