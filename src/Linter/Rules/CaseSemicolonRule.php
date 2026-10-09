<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\SwitchCases;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function strrev;
use function strspn;
use function substr;
use function trim;

/**
 * Reports a `case` or `default` label that ends with a semicolon.
 *
 * Ports the WrongOpenercase and WrongOpenerdefault checks of
 * PSR2.ControlStructures.SwitchDeclaration. PHP treats `case 1;` and `case
 * 1:` the same, so the fix is safe.
 */
final class CaseSemicolonRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/case-semicolon',
            name: 'Case semicolon',
            description: 'Reports a case or default label that ends with a semicolon instead of a colon.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Switch],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        foreach (SwitchCases::labels($file, $context->node) as $label) {
            $parts = $file->getChildren($label);
            $keyword = $parts[0] ?? null;
            $separator = self::separator($parts);
            if ($keyword === null || $separator === null || $file->getText($separator) !== ';') {
                continue;
            }

            $word = $label->kind === NodeKind::SwitchDefaultCase ? 'default' : 'case';
            $context->report(Issue::new(
                "End the {$word} label with a colon, not a semicolon.",
                $keyword->span,
            )->withEdit(self::edit($file, $parts, $separator)));
        }
    }

    /**
     * Returns the node that ends the label's head, a colon or a semicolon.
     *
     * @param list<Node> $parts The children of the label.
     */
    private static function separator(array $parts): ?Node
    {
        foreach ($parts as $part) {
            if ($part->kind === NodeKind::SwitchCaseSeparator) {
                return $part;
            }
        }

        return null;
    }

    /**
     * Replaces the semicolon with a colon. Blank space between the label's
     * last token and the semicolon goes too, so the colon sits right after
     * the label. A comment in that gap stays where it is, and so does the
     * indent of a semicolon on its own line.
     *
     * @param list<Node> $parts The children of the label.
     */
    private static function edit(SourceFile $file, array $parts, Node $separator): TextEdit
    {
        $previous = null;
        foreach ($parts as $part) {
            if ($part === $separator) {
                break;
            }

            $previous = $part;
        }

        $contents = $file->contents;
        $end = $separator->span->start;
        $start = $previous?->span->end ?? $end;
        if (trim(substr($contents, $start, $end - $start)) !== '') {
            $start = self::inlineBlankStart($contents, $end);
        }

        return TextEdit::replace(new Span($start, $separator->span->end), ':');
    }

    /**
     * Returns where the spaces and tabs in front of $offset begin. Returns
     * $offset itself when they are the indent of the line.
     */
    private static function inlineBlankStart(string $contents, int $offset): int
    {
        $start = $offset - strspn(strrev(substr($contents, offset: 0, length: $offset)), characters: " \t");

        return ($contents[$start - 1] ?? "\n") === "\n" ? $offset : $start;
    }
}
