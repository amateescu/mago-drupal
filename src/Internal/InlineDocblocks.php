<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Span;
use Mago\Sdk\Syntax\SourceFile;
use Mago\Sdk\Syntax\TriviaKind;

use function preg_match;
use function strspn;
use function substr;

/**
 * Finds docblocks that sit inside code, where nothing documents the next
 * declaration.
 *
 * A docblock is fine in front of a declaration and in front of an include or
 * a modifier that starts one, and anywhere outside a body. Inside a body, a
 * docblock must start with a tag, as `/** @var Foo $x *\/` does.
 *
 * @internal
 */
final class InlineDocblocks
{
    /**
     * What the next token may be for the docblock to belong to it. An
     * attribute opener, a declaration keyword, a modifier, an include, or an
     * enum case. Coder's list has no `readonly` and no enum case, so it
     * reports their docblocks, which PHP reads as declaration docblocks. A
     * `case` with a colon is a switch label and does not count.
     */
    private const DECLARES = '/\G(?:#\[|(?:class|interface|trait|enum|function|public|private|protected|final|static|abstract|readonly|const|include_once|include|require_once|require|var)(?![\w\x80-\xff])|case\s+[A-Za-z_\x80-\xff][\w\x80-\xff]*\s*[=;])/i';

    private function __construct() {}

    /**
     * The opening `/**` of every docblock in the file that is inside a body,
     * does not start with a tag, and is not in front of a declaration.
     *
     * An empty docblock is left out, because `no-empty-comment` reports it.
     *
     * @return list<Span>
     */
    public static function find(SourceFile $file): array
    {
        $bodies = null;
        $found = [];
        foreach ($file->getTrivia() as $index => $comment) {
            if (!self::isCandidate($file, $comment->kind, $comment->span) || Docblocks::followsOpenTag($file, $index)) {
                continue;
            }

            $bodies ??= CodeBodies::of($file);
            if ($bodies->contains($comment->span->start)) {
                $found[] = new Span($comment->span->start, $comment->span->start + 3);
            }
        }

        return $found;
    }

    /**
     * Whether a comment is a plain `/**` docblock with text that is not a tag
     * and code other than a declaration after it.
     */
    private static function isCandidate(SourceFile $file, TriviaKind $kind, Span $span): bool
    {
        // `/**/` and `/***` open comments that are not docblocks.
        if (
            $kind !== TriviaKind::DocBlockComment
            || $span->length() < 5
            || substr($file->contents, $span->start, length: 4) === '/***'
        ) {
            return false;
        }

        $after = $span->end;
        while (true) {
            $after += strspn($file->contents, characters: " \t\r\n\v\f", offset: $after);
            $end = SourceText::commentEnd($file->contents, $after);
            if ($end === null) {
                break;
            }

            $after = $end;
        }

        return (
            preg_match(self::DECLARES, $file->contents, offset: $after) !== 1
            && Docblocks::hasTextBeforeTags($file, $span)
        );
    }
}
