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

use function preg_match;
use function preg_quote;
use function substr;

/**
 * Reports the `e` modifier on preg patterns, which evaluates the replacement.
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
            description: 'Reports preg patterns using the `e` modifier, which evaluates the replacement as PHP.',
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
        if ($pattern === null || $pattern->kind !== NodeKind::LiteralString) {
            return;
        }

        // The raw text keeps the quotes, so the delimiter follows the opening
        // quote and the modifiers sit before the closing quote.
        $raw = $context->file->getText($pattern);
        $delimiter = substr($raw, offset: 1, length: 1);
        if ($delimiter === '') {
            return;
        }

        // Bracket-style delimiters close with the counterpart character.
        $closing = match ($delimiter) {
            '{' => '}',
            '(' => ')',
            '[' => ']',
            '<' => '>',
            default => $delimiter,
        };

        $modifiers = '/' . preg_quote($closing, delimiter: '/') . '[\w]{0,}e[\w]{0,}$/';
        if (preg_match($modifiers, substr($raw, offset: 0, length: -1)) !== 1) {
            return;
        }

        $context->report(Issue::new("Using the e modifier in {$name}() is a security risk.", $pattern->span)->withHelp(
            'The replacement is evaluated as PHP. Use preg_replace_callback() instead.',
        )->withLink(self::LINK));
    }
}
