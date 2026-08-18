<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\SourceFile;
use Mago\Sdk\Syntax\TriviaKind;

use function substr;
use function trim;

/**
 * Maps docblock comments back to the code they sit next to.
 *
 * Mago hands rules a flat list of trivia per file, so the association with a
 * declaration is done here by comparing spans.
 *
 * @internal
 */
final class Docblocks
{
    private function __construct() {}

    /**
     * Returns the docblock immediately above a declaration.
     *
     * Only whitespace may sit between the two. Anything else means the
     * docblock documents something other than this declaration.
     */
    public static function attachedTo(SourceFile $file, Node $declaration): ?Span
    {
        $closest = null;
        foreach ($file->getTrivia() as $trivia) {
            // Trivia comes in source order, so nothing after this point can
            // end before the declaration starts.
            if ($trivia->span->end > $declaration->span->start) {
                break;
            }

            if ($trivia->kind !== TriviaKind::DocBlockComment) {
                continue;
            }

            $closest = $trivia->span;
        }

        if ($closest === null) {
            return null;
        }

        $between = substr($file->contents, $closest->end, $declaration->span->start - $closest->end);

        return trim($between) === '' ? $closest : null;
    }
}
