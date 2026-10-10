<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Values;
use amateescu\MagoDrupal\Linter\CallRule;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\NodeKind;

use function preg_match;
use function preg_quote;
use function substr;

/**
 * Reports the `e` modifier on a preg pattern. It evaluates the replacement.
 *
 * Ports Drupal.Semantics.PregSecurity.
 *
 * @see https://www.drupal.org/node/750148
 */
final class PregSecurityRule extends CallRule
{
    private const LINK = 'https://www.drupal.org/node/750148';

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/preg-security',
            name: 'Insecure preg modifier',
            description: 'Reports a preg pattern with the `e` modifier. The modifier evaluates the replacement as PHP.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    protected function names(): array
    {
        return [
            'preg_filter',
            'preg_grep',
            'preg_match',
            'preg_match_all',
            'preg_replace',
            'preg_replace_callback',
            'preg_split',
        ];
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        $pattern = $this->argument($context, $call, 0);
        if ($pattern === null) {
            return;
        }

        // Coder reads the first token of the pattern. For `'/a/e' . $x`, that
        // is the literal before the concatenation, and the modifiers it ends
        // with stay in the full pattern.
        $first = Values::leftmost($context->file, $pattern);
        if ($first->kind !== NodeKind::LiteralString) {
            return;
        }

        // The raw text keeps the quotes. The delimiter follows the opening
        // quote, and the modifiers are before the closing quote.
        $raw = $context->file->getText($first);
        $delimiter = substr($raw, offset: 1, length: 1);
        if ($delimiter === '') {
            return;
        }

        // A bracket delimiter closes with its counterpart character.
        $closing = match ($delimiter) {
            '{' => '}',
            '(' => ')',
            '[' => ']',
            '<' => '>',
            default => $delimiter,
        };

        // The search starts after the opening delimiter and skips a delimiter
        // escaped with a backslash. Coder does neither, so it misreads the
        // first piece of `'/edit' . $x . '/'` and `'/a\/e' . $x . '/'` as a
        // pattern that ends in modifiers.
        $modifiers = '/(?<!\\\\)' . preg_quote($closing, delimiter: '/') . '[\w]{0,}e[\w]{0,}$/';
        if (preg_match($modifiers, substr($raw, offset: 2, length: -1)) !== 1) {
            return;
        }

        $context->report(Issue::new("The e modifier in {$name}() is a security risk.", $first->span)->withHelp(
            'PHP evaluates the replacement as code. Use preg_replace_callback() instead.',
        )->withLink(self::LINK));
    }
}
