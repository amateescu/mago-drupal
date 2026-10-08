<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_pop;
use function preg_match;

/**
 * Reads double-quoted strings, heredocs and nowdocs.
 *
 * @internal
 */
final class DocumentStrings
{
    private function __construct() {}

    /**
     * The text of a nowdoc, or null for any other string.
     */
    public static function nowdoc(SourceFile $file, Node $message): ?string
    {
        $matches = [];

        return preg_match("/^<<<[ \\t]*'(\\w+)'\\R(.*?)\\R?[ \\t]*\\1\\z/s", $file->getText($message), $matches) === 1
            ? $matches[2]
            : null;
    }

    /**
     * Whether a double-quoted string or a heredoc has a variable part.
     */
    public static function interpolates(SourceFile $file, Node $message): bool
    {
        if ($message->kind === NodeKind::InterpolatedString) {
            return true;
        }

        if ($message->kind !== NodeKind::CompositeString) {
            return false;
        }

        $stack = [$message];
        while (($node = array_pop($stack)) !== null) {
            if ($node->kind === NodeKind::StringPart && !self::literalPart($file, $node)) {
                return true;
            }

            foreach ($file->getChildren($node) as $child) {
                $stack[] = $child;
            }
        }

        return false;
    }

    private static function literalPart(SourceFile $file, Node $part): bool
    {
        foreach ($file->getChildren($part) as $child) {
            if ($child->kind === NodeKind::LiteralStringPart) {
                return true;
            }
        }

        return false;
    }
}
