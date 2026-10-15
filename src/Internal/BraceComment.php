<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Mago\Sdk\Syntax\Trivia;

use function rtrim;
use function str_starts_with;
use function strpos;
use function strrpos;
use function strspn;
use function substr;

/**
 * Moves a comment from the line of a class-like's opening brace into the
 * body.
 *
 * The comment goes on its own line directly above the next line with
 * content, at that line's indent, with no blank line below it. A blank line
 * there draws DrupalPractice's CommentEmptyLine. The exception is a method's
 * docblock or attributes. Coder's FunctionSpacing counts the blank line
 * before the method from the comment, so one blank line goes between them.
 * In an empty body the comment goes on the line after the brace, one level
 * deeper than the brace's line. `mago format` then adds the blank line after
 * the brace and keeps the rest.
 *
 * @internal
 */
final class BraceComment
{
    private function __construct() {}

    /**
     * The edits that move the comment, or none when code follows it on its
     * line.
     *
     * @return list<TextEdit>
     */
    public static function move(SourceFile $file, Trivia $comment, Node $class, int $brace): array
    {
        $contents = $file->contents;
        $text = rtrim($file->getText($comment->span));
        $eol = LineEnding::of($contents);
        $end = $comment->span->end;
        $next = $end + strspn($contents, characters: " \t\r\n", offset: $end);
        $close = $class->span->end - 1;
        if ($next === $close) {
            $braceLine = (int) strrpos(substr($contents, offset: 0, length: $brace), needle: "\n") + 1;
            $indent = substr($contents, $braceLine, strspn($contents, characters: " \t", offset: $braceLine));

            return [TextEdit::replace(new Span($brace + 1, $close), $eol . $indent . '  ' . $text . $eol . $indent)];
        }

        $lineEnd = strpos($contents, needle: "\n", offset: $end);
        if ($lineEnd === false || $next < $lineEnd) {
            return [];
        }

        $nextLine = (int) strrpos(substr($contents, offset: 0, length: $next), needle: "\n") + 1;
        $gap = self::opensMethod($file, $class, $next) ? $eol : '';

        return [
            TextEdit::delete(new Span($brace + 1, $end + strspn($contents, characters: " \t", offset: $end))),
            TextEdit::insert($nextLine, substr($contents, $nextLine, $next - $nextLine) . $text . $eol . $gap),
        ];
    }

    /**
     * Whether the content at the offset is the docblock or the attributes
     * of a method.
     */
    private static function opensMethod(SourceFile $file, Node $class, int $offset): bool
    {
        $opener = substr($file->contents, $offset, length: 3);
        if ($opener !== '/**' && !str_starts_with($opener, '#[')) {
            return false;
        }

        foreach ($file->getChildren($class) as $member) {
            if ($member->kind !== NodeKind::ClassLikeMember || $member->span->end <= $offset) {
                continue;
            }

            return ($file->getChildren($member)[0] ?? null)?->kind === NodeKind::Method;
        }

        return false;
    }
}
