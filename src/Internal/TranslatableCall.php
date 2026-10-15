<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_key_exists;
use function count;
use function preg_match_all;
use function strrpos;
use function substr;

use const PHP_INT_MAX;
use const PREG_OFFSET_CAPTURE;

/**
 * A call to t() or another translation entry point, for the rules that read
 * its translatable strings.
 *
 * Both rules that read these calls target the same nodes, and the worker
 * passes one `LintContext` to each of them for a node. The result for the
 * last context is kept, so the call is found once per node.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class TranslatableCall
{
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
     * How the messages name each entry point.
     */
    private const CALLEES = [
        't' => 't()',
        'translatablemarkup' => 'new TranslatableMarkup()',
        'translationwrapper' => 'new TranslationWrapper()',
        'formatplural' => 'formatPlural()',
    ];

    /**
     * Text shapes that only a file with a translation entry point has.
     *
     * `t` and `formatPlural` are too short for a bare text search, so they
     * need an anchor. The anchor is the name alone before an opening
     * parenthesis, or the name after `new` as a class name. A comment between
     * a callee and its parenthesis defeats the first anchor. Real code
     * doesn't write that.
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

    private static ?FileGate $gate = null;

    private static string $methodPath = '';

    /** @var list<int> */
    private static array $methodOffsets = [];

    private static ?LintContext $context = null;

    private static ?self $last = null;

    /**
     * @param string $name The normalized name, a class's basename for an
     *   instantiation.
     * @param string $callee How the messages name the entry point.
     * @param list<int> $positions The argument positions that hold a
     *   translatable string.
     */
    private function __construct(
        public readonly Invocation $invocation,
        public readonly string $name,
        public readonly string $callee,
        public readonly array $positions,
    ) {}

    /**
     * The call or instantiation of the context's node, or null when it is
     * not a translation entry point.
     */
    public static function of(LintContext $context): ?self
    {
        if (self::$context === $context) {
            return self::$last;
        }

        self::$context = $context;

        return self::$last = self::find($context->file, $context->node);
    }

    /**
     * How the messages name the entry point that a first-class callable such
     * as `t(...)` or `$this->t(...)` takes, or null when it takes another
     * function.
     */
    public static function callableCallee(SourceFile $file, Node $node): ?string
    {
        if (!self::gate()->passes($file) || !self::nodeMayMatch($file, $node)) {
            return null;
        }

        $name = Calls::name($file, $node);

        return $name === null ? null : self::CALLEES[Calls::normalize($name)] ?? null;
    }

    private static function find(SourceFile $file, Node $node): ?self
    {
        if (!self::gate()->passes($file) || !self::nodeMayMatch($file, $node)) {
            return null;
        }

        $invocation = Invocation::fromNode($file, $node);
        if ($invocation === null) {
            return null;
        }

        $name = Calls::normalize($invocation->name);
        if ($node->kind === NodeKind::Instantiation) {
            $name = self::basename($name);
        }

        $positions = self::TRANSLATED_ARGUMENTS[$name] ?? null;

        return $positions === null ? null : new self($invocation, $name, self::CALLEES[$name], $positions);
    }

    private static function gate(): FileGate
    {
        return self::$gate ??= new FileGate(needles: ['translatablemarkup', 'translationwrapper'], pattern: self::GATE);
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
    private static function nodeMayMatch(SourceFile $file, Node $node): bool
    {
        if ($node->kind === NodeKind::Instantiation) {
            $name = Instantiations::writtenNameFast($file, $node);

            return $name === null
            || array_key_exists(self::basename(Calls::normalize($name)), self::TRANSLATED_ARGUMENTS);
        }

        $function = $node->kind === NodeKind::FunctionCall || $node->kind === NodeKind::FunctionPartialApplication;
        if (!$function && !self::spanHoldsMethodEntryPoint($file, $node)) {
            return false;
        }

        $name = Calls::writtenNameFast($file, $node);

        return $name !== null && array_key_exists(Calls::normalize($name), self::TRANSLATED_ARGUMENTS);
    }

    /**
     * Whether a METHOD_GATE match starts inside the node's span.
     */
    private static function spanHoldsMethodEntryPoint(SourceFile $file, Node $node): bool
    {
        if (self::$methodPath !== $file->path) {
            self::$methodPath = $file->path;
            self::$methodOffsets = self::matchOffsets(self::METHOD_GATE, $file->contents);
        }

        $offsets = self::$methodOffsets;
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
}
