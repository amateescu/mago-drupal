<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\YamlFile;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\Safety;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;

use function str_contains;
use function str_repeat;
use function str_replace;
use function strcspn;
use function strlen;
use function strrpos;
use function strspn;
use function substr;

/**
 * Reports a tab character that `mago format` leaves in the file.
 *
 * Ports Generic.WhiteSpace.DisallowTabIndent for the text that the
 * formatter prints as written: the text of a comment or docblock, and the
 * HTML outside the PHP tags. The formatter writes spaces in the code and in
 * the indent of a comment's lines, so a tab in the whitespace at the start
 * of a line inside a comment is left to it.
 *
 * The rule reports each line once, as phpcs reports each comment line and
 * each line of HTML once. The fix writes 4 spaces for each tab, as phpcbf
 * does. In a comment that is safe. In HTML it changes the page output, as
 * in a `<pre>` element or a plain-text mail body, so that fix is potentially
 * unsafe. A `.yml` file is skipped: Mago parses it as HTML, and phpcs doesn't
 * report tabs there.
 */
final class TabCharacterRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/tab-character',
            name: 'Tab character',
            description: 'Reports a tab in a comment or in the HTML outside the PHP tags.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        // Most files don't hold a tab, so they stop here.
        $file = $context->file;
        if (
            !str_contains($file->contents, "\t")
            || YamlFile::is($file, suffix: '.yml')
            || YamlFile::is($file, suffix: '.yaml')
        ) {
            return;
        }

        foreach ($file->getTrivia() as $comment) {
            self::check($context, $comment->span, skipIndent: true);
        }

        foreach ($file->getNodes(NodeKind::Inline) as $html) {
            self::check($context, $html->span, skipIndent: false);
        }
    }

    /**
     * Reports each line of the span that holds a tab. $skipIndent leaves out
     * the tabs before the first other character of a line that starts inside
     * the span, which `mago format` replaces in a comment.
     *
     * @mago-expect lint:no-boolean-flag-parameter
     */
    private static function check(LintContext $context, Span $span, bool $skipIndent): void
    {
        // Each search stops at the end of the span or the line, so a file
        // with many comments and one tab is read once.
        $contents = $context->file->contents;
        $offset = $span->start;
        while (true) {
            $tab = $offset + strcspn($contents, characters: "\t", offset: $offset, length: $span->end - $offset);
            if ($tab >= $span->end) {
                return;
            }

            // A search with a negative offset runs back from the tab and
            // doesn't copy anything.
            $lineStart = strrpos($contents, needle: "\n", offset: $tab - strlen($contents));
            $lineStart = $lineStart === false ? 0 : $lineStart + 1;
            $lineEnd = $tab + strcspn($contents, characters: "\r\n", offset: $tab, length: $span->end - $tab);
            $offset = $lineEnd;

            if ($skipIndent && $lineStart > $span->start) {
                $text =
                    $lineStart
                    + strspn($contents, characters: " \t", offset: $lineStart, length: $lineEnd - $lineStart);
                $tab = $text + strcspn($contents, characters: "\t", offset: $text, length: $lineEnd - $text);
                if ($tab >= $lineEnd) {
                    continue;
                }
            }

            $context->report(self::issue($contents, $tab, $lineEnd, html: !$skipIndent));
        }
    }

    /**
     * The issue at the first tab of a line, with the edit that writes 4
     * spaces for each tab from there to $end.
     *
     * @mago-expect lint:no-boolean-flag-parameter
     */
    private static function issue(string $contents, int $tab, int $end, bool $html): Issue
    {
        $last = (int) strrpos(substr($contents, $tab, $end - $tab), needle: "\t") + $tab;
        $edit = TextEdit::replace(
            new Span($tab, $last + 1),
            str_replace("\t", str_repeat(' ', times: 4), substr($contents, $tab, $last + 1 - $tab)),
        );

        return $html
            ? Issue::new(
                'Use spaces, not tabs, in the HTML outside the PHP tags.',
                new Span($tab, $tab + 1),
            )->withEdit($edit->withSafety(Safety::PotentiallyUnsafe))
            : Issue::new('Use spaces, not tabs, in a comment.', new Span($tab, $tab + 1))->withEdit($edit);
    }
}
