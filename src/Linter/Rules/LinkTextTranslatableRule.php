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

use function str_starts_with;

/**
 * Reports untranslated link text passed to l().
 *
 * Ports Drupal.Semantics.LStringTranslatable. Only fires on Drupal 7 era code,
 * since l() was replaced by the Link class.
 */
final class LinkTextTranslatableRule extends CallRule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/link-text-translatable',
            name: 'Translatable link text',
            description: 'Reports literal link text passed to l() without wrapping it in t().',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    protected function names(): array
    {
        return ['l'];
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        $text = $this->argument($context, $call, 0, parameter: 'text');
        if ($text === null || $text->kind !== NodeKind::LiteralString) {
            return;
        }

        // Markup passed as link text is a render array label rather than a
        // sentence, so it is left alone.
        $value = Values::literalString($context->file, $text);
        if ($value === null || str_starts_with($value, '<')) {
            return;
        }

        $context->report(Issue::new('The link text passed to l() should be wrapped in t().', $text->span)->withHelp(
            'Link text is shown to users, so it needs to be translatable.',
        ));
    }
}
