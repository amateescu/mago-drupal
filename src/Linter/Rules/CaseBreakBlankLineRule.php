<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\LineEnding;
use amateescu\MagoDrupal\Internal\SourceText;
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

use function count;
use function strpos;
use function strrev;
use function strrpos;
use function strspn;
use function substr;
use function substr_count;

/**
 * Checks that one blank line follows the statement that ends a switch case.
 *
 * Ports the SpacingAfterBreak check of Squiz.ControlStructures.SwitchDeclaration.
 * A case ends with `break`, `continue`, `return`, `throw`, `exit`, `die` or
 * `goto`. The last case before the closing brace needs no blank line, and
 * neither does a `default` case. `mago format` keeps one blank line or none
 * after the statement in a brace body, so the fix stays.
 */
final class CaseBreakBlankLineRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/case-break-blank-line',
            name: 'Case break blank line',
            description: 'Checks that one blank line follows the statement that ends a switch case.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Switch],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;

        // The switch's last child wraps its body.
        $children = $file->getChildren($context->node);
        $body = $file->getChildren($children[count($children) - 1] ?? $context->node)[0] ?? null;

        // `mago format` removes the blank lines between the cases of a
        // `switch (): ... endswitch;` body, so a fix there would not stay.
        if ($body === null || $body->kind !== NodeKind::SwitchBraceDelimitedBody) {
            return;
        }

        // Cases that fall through share the statement that ends the last of
        // them. Coder checks that statement for the first case of the run,
        // so a run that starts with `default` is not checked.
        $cases = $file->getChildren($body);
        $owner = null;
        foreach ($cases as $index => $case) {
            $case = $file->getChildren($case)[0] ?? $case;
            $owner ??= $case;
            $terminator = SwitchCases::terminator($file, $case);
            if ($terminator === null) {
                continue;
            }

            if ($owner->kind === NodeKind::SwitchExpressionCase) {
                $this->check($context, $case, $terminator, $cases[$index + 1] ?? null);
            }

            $owner = null;
        }
    }

    /**
     * Checks the lines between the statement that ends a case and the next
     * code or comment.
     *
     * A comment on the statement's own line does not count. A comment on a
     * later line does, as Coder reads it. After the last case, only a
     * comment counts, since the code there is the switch's closing brace.
     * $following is the next case, or null for the last one.
     */
    private function check(LintContext $context, Node $case, Node $terminator, ?Node $following): void
    {
        $contents = $context->file->contents;
        $end = $terminator->span->end;

        $offset = SourceText::skipLineBlank($contents, $end);
        $next = $offset + strspn($contents, characters: " \t\r\n", offset: $offset);
        if ($following === null && SourceText::commentEnd($contents, $next) === null) {
            return;
        }

        $breaks = substr_count($contents, needle: "\n", offset: $end, length: $next - $end);
        if ($breaks === 2) {
            return;
        }

        $context->report(Issue::new(
            'Put one blank line after the statement that ends the case.',
            new Span($terminator->span->start, $end),
        )->withEdit(self::edit($contents, $case, $end, $next, $breaks)));
    }

    /**
     * Builds the edit that leaves one blank line between the statement at
     * $end and the code or comment at $next.
     */
    private static function edit(string $contents, Node $case, int $end, int $next, int $breaks): TextEdit
    {
        $eol = LineEnding::of($contents);

        // Code on the statement's own line moves two lines down, to the
        // indent of the case.
        if ($breaks === 0) {
            $space = $next - strspn(strrev(substr($contents, $end, $next - $end)), characters: " \t");

            return TextEdit::replace(new Span($space, $next), $eol . $eol . self::indent($contents, $case));
        }

        $firstLine = (int) strpos($contents, needle: "\n", offset: $end) + 1;
        if ($breaks === 1) {
            return TextEdit::insert($firstLine, $eol);
        }

        // Several blank lines: keep the first one.
        $secondLine = (int) strpos($contents, needle: "\n", offset: $firstLine) + 1;
        $nextLine = (int) strrpos(substr($contents, offset: 0, length: $next), needle: "\n") + 1;

        return TextEdit::delete(new Span($secondLine, $nextLine));
    }

    /**
     * Returns the spaces and tabs right before the case keyword.
     */
    private static function indent(string $contents, Node $case): string
    {
        $start = $case->span->start;
        $length = strspn(strrev(substr($contents, offset: 0, length: $start)), characters: " \t");

        return substr($contents, $start - $length, $length);
    }
}
