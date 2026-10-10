<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function count;
use function implode;
use function preg_match;
use function preg_replace;
use function trim;

/**
 * Reads the arguments of a trigger_error() deprecation notice: the level
 * that marks it, and the message flattened into the text to check.
 *
 * @internal
 */
final class DeprecationText
{
    private function __construct() {}

    /**
     * Whether a trigger_error() level argument is E_USER_DEPRECATED.
     *
     * Coder reads only the first name of the level, so a level such as
     * `E_USER_DEPRECATED | E_USER_WARNING` counts too. The constant can be
     * fully qualified, and its case does not matter.
     */
    public static function isDeprecationLevel(SourceFile $file, Node $level): bool
    {
        return preg_match('/^\\\\?E_USER_DEPRECATED\b/i', trim($file->getText($level))) === 1;
    }

    /**
     * Returns the message text, or an empty string if it cannot be read.
     *
     * A sprintf() wrapper gives its format string. Any other message gives
     * the operands of its concatenation joined by spaces. A literal gives
     * its value, an interpolated string the text between its quotes, and any
     * other operand its source text. Drupal's sniff keeps the text of a
     * constant, a call or an interpolated variable the same way.
     */
    public static function fromNode(SourceFile $file, Node $message): string
    {
        $format = self::sprintfFormat($file, $message);
        if ($format !== null) {
            return $format;
        }

        if ($message->kind === NodeKind::LiteralString) {
            return (string) Values::literalString($file, $message);
        }

        // The sniff skips a message whose first token is a variable.
        if (self::startsWithVariable($file, $message)) {
            return '';
        }

        return self::joinedParts($file, $message);
    }

    /**
     * Returns the format string if sprintf() builds the message.
     */
    private static function sprintfFormat(SourceFile $file, Node $message): ?string
    {
        if ($message->kind !== NodeKind::FunctionCall) {
            return null;
        }

        $invocation = Invocation::fromNode($file, $message);
        if ($invocation === null || !Calls::matches($invocation->name, 'sprintf')) {
            return null;
        }

        $format = $invocation->argument(0);

        return $format === null ? '' : (string) Values::literalString($file, $format);
    }

    /**
     * Whether the message starts with a variable, as in `$a . '...'`.
     */
    private static function startsWithVariable(SourceFile $file, Node $message): bool
    {
        return $file->getFirstDescendant($message, NodeKind::DirectVariable)?->span->start === $message->span->start;
    }

    /**
     * Returns the text of each operand of the message, joined by spaces.
     */
    private static function joinedParts(SourceFile $file, Node $message): string
    {
        $parts = [];
        foreach (self::operands($file, $message) as $operand) {
            $parts[] = match ($operand->kind) {
                NodeKind::LiteralString => (string) Values::literalString($file, $operand),
                NodeKind::CompositeString => self::stringContent($file, $operand),
                default => $file->getText($operand),
            };
        }

        // Adjacent literals have their own spacing, so a run of spaces from
        // the join collapses to one space.
        return trim((string) preg_replace('/ {2,}/', replacement: ' ', subject: implode(' ', $parts)));
    }

    /**
     * Returns the operands of a `.` chain in source order, or the node itself.
     *
     * @return list<Node>
     */
    private static function operands(SourceFile $file, Node $node): array
    {
        $parts = $file->getChildren($node);
        if ($node->kind !== NodeKind::Binary || count($parts) !== 3 || trim($file->getText($parts[1])) !== '.') {
            return [$node];
        }

        return [
            ...self::operands($file, Values::unwrap($file, $parts[0])),
            ...self::operands($file, Values::unwrap($file, $parts[2])),
        ];
    }

    /**
     * Returns the source text between the delimiters of an interpolated string.
     *
     * A heredoc gives its body the same way.
     */
    private static function stringContent(SourceFile $file, Node $string): string
    {
        // The composite string wraps one interpolated string or heredoc,
        // and the parts are the children of that node.
        $content = '';
        foreach ($file->getChildren($file->getChildren($string)[0] ?? $string) as $part) {
            $content .= $file->getText($part);
        }

        return $content;
    }
}
