<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Syntax\NodeKind;

use function in_array;
use function strtolower;
use function strtoupper;
use function substr;

/**
 * Reports a fully qualified `\true`, `\false` or `\null` that isn't in upper
 * case.
 *
 * Ports Generic.PHP.UpperCaseConstant for the one form `mago format` leaves
 * alone. Mago parses `\true` as a constant access, not as a literal, so the
 * formatter prints it as written. The fix writes `TRUE` without the
 * backslash. PHP never looks up these three names in a namespace, so the
 * backslash changes nothing.
 */
final class LiteralConstantCaseRule implements Rule
{
    private const NAMES = ['\true', '\false', '\null'];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/literal-constant-case',
            name: 'Literal constant case',
            description: 'Reports a fully qualified \true, \false or \null that is not written in upper case.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::ConstantAccess],
        );
    }

    public function lint(LintContext $context): void
    {
        $node = $context->node;
        $text = $context->file->getText($node);
        if (!in_array(strtolower($text), self::NAMES, strict: true)) {
            return;
        }

        $upper = strtoupper(substr($text, offset: 1));
        if ('\\' . $upper === $text) {
            return;
        }

        $issue = Issue::new("Write {$upper}, not {$text}.", $node->span);
        $context->report($issue->withEdit(TextEdit::replace($node->span, $upper)));
    }
}
