<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;

use function preg_match;
use function str_contains;
use function str_replace;
use function substr;

/**
 * Finds a string literal that escapes its own quote with a backslash, and
 * writes the same string with the other quote.
 *
 * @internal
 */
final class EscapedQuotes
{
    private function __construct() {}

    /**
     * Reports a string literal that escapes its own quote with a backslash.
     *
     * Coder tests the source text of the first token for `\\'` or `\\"`. This
     * check reads the escape pairs instead, so `'Path \\\\'` is not reported.
     * The fix writes the string with the other quote when the value stays
     * the same.
     */
    public static function check(LintContext $context, Node $literal): void
    {
        if ($literal->kind !== NodeKind::LiteralString) {
            return;
        }

        $raw = $context->file->getText($literal);
        $quote = self::quote($raw);
        if ($quote === null) {
            return;
        }

        $other = $quote === "'" ? '""' : "''";
        $issue = Issue::new(
            'Avoid backslash escaping in translatable strings when possible, use ' . $other . ' quotes instead.',
            $literal->span,
        );
        $rewritten = self::rewrite($raw);
        $context->report(
            $rewritten === null ? $issue : $issue->withEdit(TextEdit::replace($literal->span, $rewritten)),
        );
    }

    /**
     * Returns the quote character of a literal that backslash-escapes its
     * own quote and holds no quote of the other kind. Returns null for
     * every other literal.
     *
     * The text is scanned as escape pairs, so `'Path \\'` has no escaped
     * quote. A literal that mixes both quotes needs the escape.
     */
    public static function quote(string $raw): ?string
    {
        $quote = $raw[0] ?? '';
        $other = $quote === "'" ? '"' : "'";
        if ($quote !== "'" && $quote !== '"' || str_contains($raw, $other)) {
            return null;
        }

        // Escape pairs are skipped as a unit, so a backslash that ends a
        // pair never counts as the start of an escaped quote.
        $escaped = '/^.(?:[^\\\\]|\\\\[^' . $quote . '])*+\\\\' . $quote . '/s';

        return preg_match($escaped, $raw) === 1 ? $quote : null;
    }

    /**
     * Returns the literal written with the other quote, or null when that
     * could change the value.
     *
     * A single-quoted literal can move to double quotes when it has no `$`
     * and no backslash except the one before an apostrophe. A double-quoted
     * literal can move to single quotes when its only backslashes are the
     * ones before a double quote.
     */
    public static function rewrite(string $raw): ?string
    {
        $quote = self::quote($raw);
        if ($quote === null) {
            return null;
        }

        $content = substr($raw, offset: 1, length: -1);
        if ($quote === "'") {
            if (str_contains($content, '$') || preg_match('/\\\\(?!\')/', $content) === 1) {
                return null;
            }

            return '"' . str_replace(search: "\\'", replace: "'", subject: $content) . '"';
        }

        if (preg_match('/\\\\(?!")/', $content) === 1) {
            return null;
        }

        return "'" . str_replace(search: '\\"', replace: '"', subject: $content) . "'";
    }
}
