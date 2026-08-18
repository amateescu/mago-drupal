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
     * Returns the message text, or an empty string when it cannot be read.
     *
     * sprintf() wrappers contribute their format string. Anything else is read
     * as its literal parts joined by spaces, which is how Drupal's sniff treats
     * concatenated messages and interpolated constants.
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
     * Returns the format string when the message is built by sprintf().
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

        // Adjacent literals carry their own spacing, so runs of spaces from
        // the joining collapse to one.
        return trim((string) preg_replace('/ {2,}/', replacement: ' ', subject: implode(' ', $parts)));
    }
}
