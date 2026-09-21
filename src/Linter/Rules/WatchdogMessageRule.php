<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Calls;
use amateescu\MagoDrupal\Internal\Values;
use amateescu\MagoDrupal\Linter\CallRule;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\NodeKind;

use function count;

/**
 * Checks the message argument of a watchdog() call.
 *
 * Ports Drupal.Semantics.FunctionWatchdog. It reports only Drupal 7 era code,
 * because the logger channel service replaces watchdog().
 */
final class WatchdogMessageRule extends CallRule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/watchdog-message',
            name: 'Watchdog message',
            description: 'Reports a watchdog() message wrapped in t() or built by concatenation.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    protected function names(): array
    {
        return ['watchdog'];
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        $message = $this->argument($context, $call, 1, parameter: 'message');
        if ($message === null) {
            // A named or unpacked argument can hold the message without a
            // readable position, so the rule reports only a clearly short
            // call.
            if (count($call->arguments) < 2 && !self::hasUnpackedArgument($call)) {
                $context->report(Issue::new('The second argument to watchdog() is missing.', $context->node->span));
            }

            return;
        }

        if ($message->kind === NodeKind::FunctionCall) {
            $inner = Calls::name($context->file, $message);
            if ($inner !== null && Calls::matches($inner, 't')) {
                $context->report(Issue::new(
                    'Do not wrap the second argument to watchdog() in t().',
                    $message->span,
                )->withHelp('Drupal translates a log message when it shows the log, not when it writes the log.'));

                return;
            }
        }

        if (Values::concatenates($context->file, $message)) {
            $context->report(Issue::new(
                'Do not concatenate a translatable string. Use placeholders instead.',
                $message->span,
            ));
        }
    }

    /**
     * Whether any argument is a `...` spread.
     */
    private static function hasUnpackedArgument(CallExpression $call): bool
    {
        foreach ($call->arguments as $argument) {
            if ($argument->unpacked) {
                return true;
            }
        }

        return false;
    }
}
