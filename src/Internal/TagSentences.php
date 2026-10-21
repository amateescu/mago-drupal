<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;

use function array_reverse;
use function array_slice;
use function in_array;
use function ltrim;
use function mb_substr;
use function preg_match;
use function rtrim;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strspn;
use function strtoupper;
use function substr;
use function trim;

/**
 * The wording checks of Drupal.Commenting.FunctionComment on the `@param`
 * and `@throws` descriptions, for `drupal/doc-sentence`: a capital letter
 * at the start and terminal punctuation at the end.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class TagSentences
{
    private function __construct() {}

    /**
     * Checks that a `@param` description has a capital letter and ends with
     * terminal punctuation.
     *
     * @param list<DocblockTag> $tags Every tag in the docblock. The check
     *   uses them to find whether an example follows the description.
     */
    public static function checkParam(LintContext $context, array $tags, ParamTag $param): void
    {
        // Coder wants an upper-case letter anywhere in the first line of the
        // description, so `lower Case.` passes and `_lower.` does not. It
        // reports that line.
        $tag = $param->tag;
        $first = self::firstLineBelow($tag, $param->variable);
        if (preg_match('/\p{Lu}/u', $first === null ? $param->description : $first->text) === 0) {
            $context->report(Issue::new(
                'The param description must start with a capital letter.',
                $first === null ? $tag->contentSpan() : self::textSpan($first),
            ));
        }

        // A description with an example after it ends on the example, not on
        // a sentence. The ported sniff exempts that. Coder reports a missing
        // full stop on the description's last line.
        if (!self::precedesExample($tags, $tag)) {
            $last = self::lastTextLine($tag);
            self::checkProseEnd(
                $context,
                $param->description,
                $last === null ? $tag->contentSpan() : self::textSpan($last),
                'param description',
                self::fullStop($tag),
            );
        }
    }

    /**
     * Checks that a `@throws` description starts with a capital letter and
     * ends with terminal punctuation. A type-only `@throws` doesn't
     * need a description.
     */
    public static function checkThrows(LintContext $context, DocblockTag $tag): void
    {
        [$type, $rest] = DocType::split($tag->content());
        if ($type === null || $rest === '') {
            return;
        }

        $span = self::textBelowSpan($tag);
        $label = '@throws description';
        // Coder runs `strtoupper()` on the first byte, so only a lower-case
        // ASCII letter is reported.
        $first = substr($rest, offset: 0, length: 1);
        if ($first !== strtoupper($first)) {
            $context->report(Issue::new("The {$label} must start with a capital letter.", $span));
        }

        self::checkProseEnd($context, $rest, $span, $label);
    }

    /**
     * Whether an example follows a `@param` description.
     *
     * Drupal writes the example inside the description. It is either
     * indented with the text, or at the star column, where it parses as a
     * tag of its own. Coder skips the same markers when it reads a param
     * comment. With both spellings, the check for terminal punctuation thus
     * applies to the description, not to the example's last line. Coder
     * skips `@link` the same way, but a link is not an example. The
     * description before it is prose and keeps its check.
     *
     * @param list<DocblockTag> $tags
     */
    private static function precedesExample(array $tags, DocblockTag $tag): bool
    {
        if (self::endsInExample($tag)) {
            return true;
        }

        $index = $tag->indexIn($tags);
        if ($index === null) {
            return false;
        }

        $next = $tags[$index + 1] ?? null;

        return $next !== null && in_array($next->name, Docblocks::EXAMPLE_TAGS, strict: true);
    }

    /**
     * Whether a tag's last line is a `@code` example, not prose.
     */
    private static function endsInExample(DocblockTag $tag): bool
    {
        foreach (array_reverse($tag->lines) as $line) {
            $text = trim($line->text);
            if ($text === '') {
                continue;
            }

            return str_starts_with($text, '@code') || str_starts_with($text, '@endcode');
        }

        return false;
    }

    /**
     * The first line of text below the line that holds the variable, or
     * null when there is none. The variable is on the tag's own line unless
     * the type spans several lines.
     */
    private static function firstLineBelow(DocblockTag $tag, string $variable): ?DocblockLine
    {
        $below = false;
        foreach ($tag->lines as $line) {
            if ($below && trim($line->text) !== '') {
                return $line;
            }

            $below = $below || str_contains($line->text, $variable);
        }

        return null;
    }

    /**
     * The tag's last line with text, or null when every line is blank.
     */
    private static function lastTextLine(DocblockTag $tag): ?DocblockLine
    {
        foreach (array_reverse($tag->lines) as $line) {
            if (trim($line->text) !== '') {
                return $line;
            }
        }

        return null;
    }

    /**
     * The span of a line's text, without the whitespace around it.
     */
    private static function textSpan(DocblockLine $line): Span
    {
        $start = $line->offset + strspn($line->text, characters: " \t");

        return new Span($start, $line->offset + strlen(rtrim($line->text)));
    }

    /**
     * The span from the first text below the tag's own line to the end of the
     * tag's last text, or the tag's content when the tag doesn't have text
     * below. Coder reports a missing capital letter or full stop in a `@throws`
     * description on the first line below the tag.
     */
    private static function textBelowSpan(DocblockTag $tag): Span
    {
        $first = null;
        $last = null;
        foreach (array_slice($tag->lines, offset: 1) as $line) {
            if (trim($line->text) === '') {
                continue;
            }

            $first ??= $line;
            $last = $line;
        }

        if ($first === null || $last === null) {
            return $tag->contentSpan();
        }

        return new Span(self::textSpan($first)->start, self::textSpan($last)->end);
    }

    /**
     * Checks that free-form text ends with terminal punctuation.
     */
    private static function checkProseEnd(
        LintContext $context,
        string $text,
        Span $span,
        string $label,
        ?TextEdit $fix = null,
    ): void {
        $last = mb_substr(rtrim($text), -1);
        if (in_array($last, ['.', '!', '?', ')'], strict: true)) {
            return;
        }

        $issue = Issue::new("The {$label} must end with terminal punctuation.", $span);
        $context->report($fix === null ? $issue : $issue->withEdit($fix));
    }

    /**
     * The edit that adds a full stop to a tag's last line, or null when that
     * line ends in something a period would spoil: a url, a tag such as an
     * indented `@see`, or a colon, comma or semicolon that announces more.
     */
    private static function fullStop(DocblockTag $tag): ?TextEdit
    {
        foreach (array_reverse($tag->lines) as $line) {
            $text = rtrim($line->text);
            if ($text === '') {
                continue;
            }

            $trimmed = ltrim($text);
            if (
                str_starts_with($trimmed, '@')
                || preg_match('~https?://\S+$~', $text) === 1
                || preg_match('/[:,;]$|\.["\']$/', $text) === 1
            ) {
                return null;
            }

            return TextEdit::insert($line->offset + strlen($text), '.');
        }

        return null;
    }
}
