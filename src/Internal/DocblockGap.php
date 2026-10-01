<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\SourceFile;

use function strlen;
use function strrpos;
use function strspn;
use function substr;
use function substr_count;

/**
 * Builds the edits for the line breaks between a docblock and the code
 * below it.
 *
 * @internal
 */
final class DocblockGap
{
    private function __construct() {}

    /**
     * Reports a declaration that does not start on the line right below its
     * docblock, with the fix that puts it there.
     */
    public static function checkBelow(LintContext $context, Span $docblock, string $declaration): void
    {
        $edit = self::noBlankLine($context->file, $docblock);
        if ($edit === null) {
            return;
        }

        $context->report(Issue::new(
            "Put the {$declaration} on the line right below its docblock.",
            new Span($docblock->end - 2, $docblock->end),
        )->withEdit($edit));
    }

    /**
     * The edit that puts the code right on the line below the docblock, or
     * null when it is there already or nothing follows.
     */
    private static function noBlankLine(SourceFile $file, Span $docblock): ?TextEdit
    {
        [$next, $breaks] = self::measure($file->contents, $docblock);
        if ($breaks === 1 || $next === null) {
            return null;
        }

        if ($breaks === 0) {
            $indent = DocblockRows::indent($file, $docblock) ?? '';

            return TextEdit::replace(new Span($docblock->end, $next), LineEnding::of($file->contents) . $indent);
        }

        // The last line break and the indent after it stay.
        $gap = substr($file->contents, $docblock->end, $next - $docblock->end);
        $last = (int) strrpos($gap, needle: "\n");
        $keep = $last > 0 && $gap[$last - 1] === "\r" ? $last - 1 : $last;

        return TextEdit::delete(new Span($docblock->end, $docblock->end + $keep));
    }

    /**
     * The edit that adds a blank line between the docblock and the code
     * below it, or null when there is one already or nothing follows.
     */
    public static function blankLine(SourceFile $file, Span $docblock): ?TextEdit
    {
        [$next, $breaks] = self::measure($file->contents, $docblock);
        if ($breaks > 1 || $next === null) {
            return null;
        }

        $eol = LineEnding::of($file->contents);

        return $breaks === 1
            ? TextEdit::insert($docblock->end, $eol)
            : TextEdit::replace(new Span($docblock->end, $next), $eol . $eol);
    }

    /**
     * Where the code after the docblock starts, or null at the end of the
     * file, and how many line breaks come before it.
     *
     * @return array{?int, int}
     */
    private static function measure(string $contents, Span $docblock): array
    {
        $next = $docblock->end + strspn($contents, characters: " \t\r\n", offset: $docblock->end);
        $breaks = substr_count($contents, needle: "\n", offset: $docblock->end, length: $next - $docblock->end);

        return [$next >= strlen($contents) ? null : $next, $breaks];
    }
}
