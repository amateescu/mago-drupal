<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Calls;
use amateescu\MagoDrupal\Internal\DocumentStrings;
use amateescu\MagoDrupal\Internal\FileGate;
use amateescu\MagoDrupal\Internal\Instantiations;
use amateescu\MagoDrupal\Internal\Invocation;
use amateescu\MagoDrupal\Internal\SourceText;
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

use function array_key_exists;
use function count;
use function ctype_digit;
use function in_array;
use function preg_match_all;
use function strcspn;
use function strip_tags;
use function strrpos;
use function substr;
use function trim;

use const PHP_INT_MAX;
use const PREG_OFFSET_CAPTURE;

/**
 * Checks the strings that a call passes to t() and the other translation entry
 * points.
 *
 * Ports Drupal.Semantics.FunctionT. A translatable string must be a whole
 * literal, because the extractor reads the source and does not run it. A
 * nowdoc counts as a literal, as in Coder 9; a heredoc does not. A string
 * concatenated after the call is reported too, as in `t('Name') . ':'`.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class TranslatableStringRule implements Rule
{
    private const HELP = 'The string extractor reads the source code, so it sees only whole literals.';

    /**
     * The strings that may follow a translated call after a `.`, as Coder's
     * checkConcatString() lists them. They are compared once the quotes,
     * HTML tags and spaces are gone, and an empty result is fine too. `\n`
     * is the two characters, as written between the quotes.
     */
    private const CONCAT_ALLOWED = ['(', ')', '[', ']', '-', '<', '>', '«', '»', '\\n'];

    /**
     * Entry points mapped to the argument positions that hold a translatable
     * string. formatPlural() takes the count first, so its strings are second
     * and third.
     */
    private const TRANSLATED_ARGUMENTS = [
        't' => [0],
        'translatablemarkup' => [0],
        'translationwrapper' => [0],
        'formatplural' => [1, 2],
    ];

    /**
     * Text shapes that only a file with a translation entry point has.
     *
     * `t` and `formatPlural` are too short for a bare text search, so they
     * need an anchor. The anchor is the name alone before an opening
     * parenthesis, or the name after `new` as a class name. A comment between
     * a callee and its parenthesis defeats the first anchor. Nothing writes
     * that.
     */
    private const GATE = '/(?<!\w)(?:t|formatplural)\s*\(|new\s+[\w\\\\]*(?:t|formatplural)\b/i';

    /**
     * The shape of a method or static call to a translation entry point.
     *
     * Every such call has its selector between an arrow or double colon and
     * an opening parenthesis. The file-wide match offsets show which spans
     * can hold one. The same comment limit as for GATE applies.
     */
    private const METHOD_GATE = '/(?:->|::)\s*(?:t|formatplural|translatablemarkup|translationwrapper)\s*\(/i';

    private ?FileGate $gate = null;

    private string $methodPath = '';

    /** @var list<int> */
    private array $methodOffsets = [];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/translatable-string',
            name: 'Translatable string',
            description: 'Checks that a translatable string is one literal without concatenation or padding.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [...Calls::CALL_KINDS, NodeKind::Instantiation],
        );
    }

    public function lint(LintContext $context): void
    {
        $this->gate ??= new FileGate(needles: ['translatablemarkup', 'translationwrapper'], pattern: self::GATE);
        if (!$this->gate->passes($context->file) || !$this->nodeMayMatch($context->file, $context->node)) {
            return;
        }

        $invocation = Invocation::fromNode($context->file, $context->node);
        if ($invocation === null) {
            return;
        }

        $name = Calls::normalize($invocation->name);
        if ($context->node->kind === NodeKind::Instantiation) {
            $name = self::basename($name);
        }

        $positions = self::TRANSLATED_ARGUMENTS[$name] ?? null;
        if ($positions === null) {
            return;
        }

        // A call with only named or only unpacked arguments is not empty. It
        // has no readable positional argument, so the rule does not check it.
        if ($invocation->isEmpty()) {
            $context->report(Issue::new('Do not call t() without arguments.', $context->node->span));

            return;
        }

        // Coder checks the text after the call only when the message starts
        // with a string literal and is not empty. It returns before the check
        // for any other message.
        $literal = true;
        foreach ($positions as $position) {
            $message = $invocation->argument($position);
            if ($message !== null) {
                $this->check($context, $message);
            }

            $literal = $literal && $message !== null && self::startsWithLiteral($context->file, $message);
        }

        if ($literal) {
            $this->checkConcatAfter($context);
        }
    }

    /**
     * Cheap check of the written name, done before the precise derivation.
     *
     * A NULL fast name rejects a call node at once, because the precise walk
     * cannot find a written name for such a call either. For an instantiation
     * NULL means unknown, so the node stays in. A candidate that passes still
     * goes through the precise path. That path derives and checks the name
     * again, so it rejects an over-matched curried call.
     *
     * A method or static call skips the fast derivation too, unless the
     * file-wide METHOD_GATE offsets put a matching selector inside its span.
     * This excludes such calls at the cost of one comparison each.
     */
    private function nodeMayMatch(SourceFile $file, Node $node): bool
    {
        if ($node->kind === NodeKind::Instantiation) {
            $name = Instantiations::writtenNameFast($file, $node);

            return $name === null
            || array_key_exists(self::basename(Calls::normalize($name)), self::TRANSLATED_ARGUMENTS);
        }

        if ($node->kind !== NodeKind::FunctionCall && !$this->spanHoldsMethodEntryPoint($file, $node)) {
            return false;
        }

        $name = Calls::writtenNameFast($file, $node);

        return $name !== null && array_key_exists(Calls::normalize($name), self::TRANSLATED_ARGUMENTS);
    }

    /**
     * Whether a METHOD_GATE match starts inside the node's span.
     */
    private function spanHoldsMethodEntryPoint(SourceFile $file, Node $node): bool
    {
        if ($this->methodPath !== $file->path) {
            $this->methodPath = $file->path;
            $this->methodOffsets = self::matchOffsets(self::METHOD_GATE, $file->contents);
        }

        $offsets = $this->methodOffsets;
        $low = 0;
        $high = count($offsets) - 1;
        while ($low <= $high) {
            $middle = ($low + $high) >> 1;
            if ($offsets[$middle] < $node->span->start) {
                $low = $middle + 1;
                continue;
            }

            $high = $middle - 1;
        }

        return ($offsets[$low] ?? PHP_INT_MAX) < $node->span->end;
    }

    /**
     * Returns the sorted byte offsets where a pattern matches.
     *
     * @return list<int>
     */
    private static function matchOffsets(string $pattern, string $contents): array
    {
        $matches = [];
        preg_match_all($pattern, $contents, $matches, flags: PREG_OFFSET_CAPTURE);
        // The stub for preg_match_all() does not model the offset-capture
        // shape. In that shape each match is a value and byte offset pair.
        // @mago-expect analysis:docblock-type-mismatch
        /** @var list<array{string, int}> $pairs */
        $pairs = $matches[0];
        $offsets = [];
        foreach ($pairs as $pair) {
            $offsets[] = $pair[1];
        }

        return $offsets;
    }

    /**
     * Reduces a qualified name to its basename.
     *
     * The sniff matches class basenames, so a qualified
     * `new \Foo\TranslatableMarkup()` counts the same as the imported form.
     */
    private static function basename(string $name): string
    {
        $separator = strrpos($name, needle: '\\');

        return $separator === false ? $name : substr($name, $separator + 1);
    }

    /**
     * Checks one translatable string argument.
     */
    private function check(LintContext $context, Node $message): void
    {
        $nowdoc = $message->kind === NodeKind::CompositeString
            ? DocumentStrings::nowdoc($context->file, $message)
            : null;
        if ($nowdoc !== null) {
            if ($nowdoc === '') {
                $context->report(Issue::new('Do not pass an empty string to t().', $message->span));
            }

            return;
        }

        if ($message->kind !== NodeKind::LiteralString) {
            $this->reportNonLiteral($context, $message);

            return;
        }

        $value = Values::literalString($context->file, $message);
        if ($value === null) {
            return;
        }

        if ($value === '') {
            $context->report(Issue::new('Do not pass an empty string to t().', $message->span));

            return;
        }

        if ($value !== trim($value)) {
            $context->report(Issue::new(
                'Do not start or end a translatable string with whitespace.',
                $message->span,
            )->withHelp('Use placeholders for the variable parts instead of padding the literal.'));
        }
    }

    /**
     * Whether a message starts with a string literal or a nowdoc, and is not
     * an empty one on its own, the way Coder reads its first token.
     */
    private static function startsWithLiteral(SourceFile $file, Node $message): bool
    {
        $first = $message;
        while ($first->kind === NodeKind::Binary) {
            $left = $file->getChildren($first)[0] ?? null;
            if ($left === null) {
                return false;
            }

            $first = Values::unwrap($file, $left);
        }

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

    /**
     * Reports an argument that is not a plain literal.
     *
     * Concatenation and interpolation each get their own message. Both are
     * frequent, and a message that names the mistake makes the fix clear.
     */
    private function reportNonLiteral(LintContext $context, Node $message): void
    {
        if (Values::concatenates($context->file, $message)) {
            $context->report(Issue::new(
                'Do not concatenate a translatable string. Use placeholders instead.',
                $message->span,
            )->withHelp(self::HELP));

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
        $issue = Issue::new('Pass only a string literal to t() where possible.', $message->span)->withHelp(self::HELP);
        $context->report($issue);
    }
}
