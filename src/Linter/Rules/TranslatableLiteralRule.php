<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Calls;
use amateescu\MagoDrupal\Internal\DocumentStrings;
use amateescu\MagoDrupal\Internal\SourceText;
use amateescu\MagoDrupal\Internal\TranslatableCall;
use amateescu\MagoDrupal\Internal\Values;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function ctype_digit;
use function in_array;
use function preg_replace;
use function strcspn;
use function strip_tags;
use function substr;
use function trim;

/**
 * Checks that t() and the other translation entry points get a string
 * literal, and that a string isn't joined after the call.
 *
 * Ports `NotLiteralString` and `ConcatString` of Drupal.Semantics.FunctionT.
 * The string extractor reads the source and does not run it, so it sees
 * only a literal, and a first-class callable such as `t(...)` hides the
 * string from it. `drupal/translatable-string` reports a string built with
 * `.`.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class TranslatableLiteralRule implements Rule
{
    private const HELP = 'The string extractor reads the source code, so it sees only whole literals.';

    /**
     * The first-class callable forms of the calls the rule reads.
     */
    private const CALLABLE_KINDS = [
        NodeKind::FunctionPartialApplication,
        NodeKind::MethodPartialApplication,
        NodeKind::StaticMethodPartialApplication,
    ];

    /**
     * The strings that may follow a translated call after a `.`, as Coder's
     * checkConcatString() lists them. They are compared once the quotes,
     * HTML tags and spaces are gone, and an empty result is fine too. `\n`
     * is the two characters, as written between the quotes.
     */
    private const CONCAT_ALLOWED = ['(', ')', '[', ']', '-', '<', '>', '«', '»', '\\n'];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/translatable-literal',
            name: 'Translatable literal',
            description: "Reports a translatable string that isn't a literal, a first-class callable such as t(...), and a string joined after the call.",
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [...Calls::CALL_KINDS, NodeKind::Instantiation, ...self::CALLABLE_KINDS],
        );
    }

    public function lint(LintContext $context): void
    {
        if (in_array($context->node->kind, self::CALLABLE_KINDS, strict: true)) {
            self::checkCallable($context);

            return;
        }

        $call = TranslatableCall::of($context);
        if ($call === null || $call->invocation->isEmpty()) {
            return;
        }

        // Coder checks the text after the call only when the message starts
        // with a string literal and is not empty. It returns before the check
        // for any other message.
        $literal = true;
        foreach ($call->positions as $position) {
            $message = $call->invocation->argument($position);
            if ($message !== null) {
                $this->check($context, $message, $call->callee);
            }

            $literal = $literal && $message !== null && self::startsWithLiteral($context->file, $message);
        }

        if ($literal) {
            $this->checkConcatAfter($context);
        }
    }

    /**
     * Reports a first-class callable such as `t(...)`. A partial
     * application with arguments is left out.
     */
    private static function checkCallable(LintContext $context): void
    {
        $callee = TranslatableCall::callableCallee($context->file, $context->node);
        if ($callee === null) {
            return;
        }

        foreach ($context->file->getChildren($context->node) as $child) {
            if (
                $child->kind !== NodeKind::PartialArgumentList
                || preg_replace('/\s+/', replacement: '', subject: $context->file->getText($child)) !== '(...)'
            ) {
                continue;
            }

            $context->report(Issue::new(
                'Call ' . $callee . ' with a string literal, not as a first-class callable.',
                $context->node->span,
            )->withHelp(self::HELP));
        }
    }

    /**
     * Reports an argument that is neither a literal nor a string built with
     * `.`. Interpolation gets its own message, since it is frequent and a
     * message that names it makes the fix clear.
     */
    private function check(LintContext $context, Node $message, string $callee): void
    {
        if (
            $message->kind === NodeKind::LiteralString
            || $message->kind === NodeKind::CompositeString
            && DocumentStrings::nowdoc($context->file, $message) !== null
            || Values::concatenates($context->file, $message)
        ) {
            return;
        }

        if (DocumentStrings::interpolates($context->file, $message)) {
            $context->report(Issue::new(
                'Do not interpolate a variable into a translatable string. Use placeholders instead.',
                $message->span,
            )->withHelp(self::HELP));

            return;
        }

        // A variable, a constant or a heredoc gets here. A call sometimes
        // passes one on purpose, so the message says "where possible".
        $issue = Issue::new('Pass only a string literal to ' . $callee . ' where possible.', $message->span)->withHelp(
            self::HELP,
        );
        $context->report($issue);
    }

    /**
     * Whether a message starts with a string literal or a nowdoc, and is not
     * an empty one on its own, the way Coder reads its first token.
     */
    private static function startsWithLiteral(SourceFile $file, Node $message): bool
    {
        $first = Values::leftmost($file, $message);
        $whole = $first->id === $message->id;
        if ($first->kind === NodeKind::CompositeString) {
            $nowdoc = DocumentStrings::nowdoc($file, $first);

            return $nowdoc !== null && ($nowdoc !== '' || !$whole);
        }

        return $first->kind === NodeKind::LiteralString && (!$whole || Values::literalString($file, $first) !== '');
    }

    /**
     * Reports a string literal that a `.` joins to the end of the call, as
     * in `t('Name') . ':'`.
     *
     * Ports the ConcatString check of the sniff. Coder reads only the first
     * line of a string that spans lines. It skips a double-quoted string
     * with a variable in it, and a `b'...'` binary string.
     */
    private function checkConcatAfter(LintContext $context): void
    {
        $contents = $context->file->contents;
        $operator = SourceText::skipBlank($contents, $context->node->span->end);
        $next = $contents[$operator + 1] ?? '';
        if (($contents[$operator] ?? '') !== '.' || $next === '=' || $next === '.' || ctype_digit($next)) {
            return;
        }

        $start = SourceText::skipBlank($contents, $operator + 1);
        $end = SourceText::constantStringEnd($contents, $start);
        if ($end === null) {
            return;
        }

        // The first line keeps its line break, so a quote before the break
        // stays, as in Coder's token.
        $text = substr($contents, $start, $end - $start);
        $string = trim(strip_tags(trim(
            substr($text, offset: 0, length: strcspn($text, characters: "\n") + 1),
            characters: '"\'',
        )));
        if ($string === '' || in_array($string, self::CONCAT_ALLOWED, strict: true)) {
            return;
        }

        $context->report(Issue::new(
            'Do not concatenate a string to a translatable string. Make it part of the translatable string and use placeholders.',
            new Span($start, $end),
        )->withHelp(
            'Translators see only the string inside the call, so they cannot translate or move the added text.',
        ));
    }
}
