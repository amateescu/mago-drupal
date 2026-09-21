<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Calls;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function ltrim;
use function stripos;
use function strtoupper;
use function trim;

/**
 * Reports a deprecation notice that is not silenced with `@`.
 *
 * Ports Drupal.Semantics.UnsilencedDeprecation. Drupal's own error handler
 * turns a deprecation into a test failure, so an unsilenced notice breaks
 * every test that runs the code.
 *
 * The rule reads the calls from the file's target list, the same way that
 * `DeprecationMessageRule` does. That is why `FunctionCall` is a target. The
 * per-call dispatches do nothing.
 */
final class UnsilencedDeprecationRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/unsilenced-deprecation',
            name: 'Unsilenced deprecation',
            description: 'Reports a trigger_error() deprecation notice that does not start with "@".',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program, NodeKind::FunctionCall],
        );
    }

    public function lint(LintContext $context): void
    {
        if ($context->node->kind !== NodeKind::Program) {
            return;
        }

        // PHP function names are case-insensitive, so the text search is too.
        if (stripos($context->file->contents, needle: 'trigger_error') === false) {
            return;
        }

        $calls = Calls::findFunctionsInTargets($context->file, within: null, names: ['trigger_error'])['trigger_error']
        ?? [];
        foreach ($calls as $call) {
            if (!$this->isDeprecation($context->file, $call) || $this->isSilenced($context->file, $call)) {
                continue;
            }

            $context->report(Issue::new(
                'Silence a deprecation notice with "@".',
                $call->span,
            )->withEdit(TextEdit::insert($call->span->start, text: '@'))->withHelp(
                'Drupal reports an unsilenced deprecation as a test failure.',
            ));
        }
    }

    /**
     * Whether a trigger_error() call raises a deprecation.
     */
    private function isDeprecation(SourceFile $file, Node $call): bool
    {
        $expression = CallExpression::fromNode($file, $call);
        $level = Calls::argument($file, $expression, index: 1, parameter: 'error_level');

        // The constant can be fully qualified, so the rule removes the
        // leading backslash before the comparison.
        return (
            $level !== null
            && strtoupper(ltrim(trim($file->getText($level)), characters: '\\')) === 'E_USER_DEPRECATED'
        );
    }

    /**
     * Whether an `@` is directly before the call.
     */
    private function isSilenced(SourceFile $file, Node $call): bool
    {
        // The `@` must touch the call. `@ trigger_error(...)` silences the
        // call at run time, but the ported sniff reports it.
        $offset = $call->span->start - 1;

        return $offset >= 0 && $file->contents[$offset] === '@';
    }
}
