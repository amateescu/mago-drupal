<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Linter\CallRule;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\NodeKind;

/**
 * Reports calls to the devel module's dump helpers and to functions that
 * some PHP builds do not have.
 *
 * Ports phpstan-drupal's DiscouragedFunctionsRule. Do not commit the devel
 * module's dump helpers. Some platforms do not have `fnmatch()`.
 */
final class DiscouragedFunctionRule extends CallRule
{
    private const DEVEL = [
        'dargs',
        'dcp',
        'dd',
        'dfb',
        'dfbt',
        'dpm',
        'dpq',
        'dpr',
        'dprint_r',
        'drupal_debug',
        'dsm',
        'dvm',
        'dvr',
        'kdevel_print_object',
        'kpr',
        'kprint_r',
        'sdpm',
    ];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/discouraged-function',
            name: 'Discouraged function',
            description: 'Reports calls to the devel dump helpers and to fnmatch(). Some PHP builds do not have fnmatch().',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    protected function names(): array
    {
        return [...self::DEVEL, 'fnmatch'];
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        $help = $name === 'fnmatch'
            ? 'Some PHP builds do not have fnmatch(). Use preg_match() instead.'
            : "Do not commit the devel module's debug output.";
        $context->report(Issue::new("Do not call {$name}().", $context->node->span)->withHelp($help));
    }
}
