<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function implode;
use function preg_replace;
use function trim;

/**
 * Flattens a deprecation message argument into the text to check.
 *
 * @internal
 */
final class DeprecationText
{
    private function __construct() {}

    /**
     * Returns the message text, or an empty string if it cannot be read.
     *
     * A sprintf() wrapper gives its format string. For any other shape, the
     * result is its literal parts joined by spaces. Drupal's sniff treats
     * concatenated messages and interpolated constants the same way.
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

        return self::literalParts($file, $message);
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

    private static function literalParts(SourceFile $file, Node $message): string
    {
        $parts = [];
        foreach ($file->getDescendants($message) as $descendant) {
            $part = match ($descendant->kind) {
                NodeKind::LiteralString => (string) Values::literalString($file, $descendant),
                NodeKind::LiteralStringPart => $file->getText($descendant),
                default => null,
            };

            if ($part !== null) {
                $parts[] = $part;
            }
        }

        // Adjacent literals have their own spacing, so a run of spaces from
        // the join collapses to one space.
        return trim((string) preg_replace('/ {2,}/', replacement: ' ', subject: implode(' ', $parts)));
    }
}
