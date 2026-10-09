<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\LiteralText;
use amateescu\MagoDrupal\Linter\CallRule;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\NodeKind;

use function preg_match;
use function strrpos;
use function strtr;
use function substr;

/**
 * Reports a define() constant name that is not upper case.
 *
 * Ports Generic.NamingConventions.UpperCaseConstantName.ConstantNotUpperCase.
 * Mago's own `constant-name` rule covers `const`.
 */
final class DefineNameRule extends CallRule
{
    private const LOWER = 'abcdefghijklmnopqrstuvwxyz';

    private const UPPER = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/define-name',
            name: 'Define name',
            description: 'Reports define() constants whose name is not upper case.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    protected function names(): array
    {
        return ['define'];
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        $argument = $this->argument($context, $call, 0, 'constant_name');
        if ($argument === null) {
            return;
        }

        $constant = LiteralText::of($context->file, $argument);
        if ($constant === null) {
            return;
        }

        // A namespace in the name stays as written. Only the last segment
        // must be upper case.
        $position = strrpos($constant, needle: '\\');
        $cut = $position === false ? 0 : $position + 1;
        $prefix = substr($constant, offset: 0, length: $cut);
        $segment = substr($constant, offset: $cut);

        // The check is ASCII only, as strtoupper() is in PHP 8.2 and later.
        if (preg_match('/[a-z]/', $segment) !== 1) {
            return;
        }

        $expected = $prefix . strtr($segment, from: self::LOWER, to: self::UPPER);
        $context->report(Issue::new(
            "The constant name '{$constant}' must be upper case, use '{$expected}'.",
            $argument->span,
        ));
    }
}
